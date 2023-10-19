<?php

namespace OpenSoutheners\SidecarLocal\Commands;

use Hammerstone\Sidecar\Clients\LambdaClient;
use Hammerstone\Sidecar\Sidecar;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

class DeployLocal extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'sidecar:local';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deploy Sidecar functions locally using AWS Lambda Docker images';

    public function __construct(protected Filesystem $filesystem)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! class_exists('Hammerstone\Sidecar\Sidecar')) {
            $this->error('You must first install hammerstone/sidecar composer package.');

            return 1;
        }

        if (config('sidecar.env') !== 'local') {
            $this->error('Make sure you have sidecar.env or app.env as "local".');

            return 2;
        }

        $fullPath = base_path($this->option('path'));

        if ($this->option('stop')) {
            return $this->stopDocker($fullPath);
        }

        if (! $this->writeComposeFile($fullPath, $this->buildYamlServices($fullPath))) {
            $this->error('Error writing the docker-compose.yml file.');

            return 3;
        }

        $this->info('Docker compose file created successfully with all functions as services.');

        if ($this->option('run')) {
            return $this->runInDocker($fullPath);
        }

        return 0;
    }

    /**
     * Build function services array to be written by Yaml dumper.
     */
    protected function buildYamlServices(string $basePath): array
    {
        /** @var array<\Hammerstone\Sidecar\LambdaFunction> $sidecarFunctions */
        $sidecarFunctions = Sidecar::instantiatedFunctions();
        $services = [];

        foreach ($sidecarFunctions as $lambdaFunction) {
            $lambdaFunction->beforeDeployment();

            $functionClassName = class_basename(get_class($lambdaFunction));
            $functionLayers = $lambdaFunction->layers();
            $functionLayersPath = null;

            if (count($functionLayers) > 0) {
                $functionLayersPath = $this->grabFunctionRequiredLayers($basePath, $functionClassName, $functionLayers);
            }

            $functionAwsCallName = $lambdaFunction->nameWithPrefix();
            $serviceName = Str::snake(Str::remove('-', $functionClassName));

            $image = Str::replaceLast('.x', '', $lambdaFunction->runtime());
            $imageRuntime = preg_replace('/\d|/', '', $image);
            $imageRuntimeTag = filter_var($image, FILTER_SANITIZE_NUMBER_INT);
            $awsLambdaImage = "amazon/aws-lambda-{$imageRuntime}:{$imageRuntimeTag}";

            $services[$serviceName] = [
                'container_name' => $functionClassName,
                'image' => $awsLambdaImage,
                'deploy' => [
                    'restart_policy' => [
                        'condition' => 'on-failure',
                        'max_attempts' => 3,
                    ],
                ],
                'command' => $lambdaFunction->handler(),
                'volumes' => ["./{$functionClassName}:/var/task"],
                'labels' => [
                    "traefik.enable=true",
                    "traefik.http.routers.{$serviceName}.entrypoints=web",
                    "traefik.http.routers.{$serviceName}.rule=Host(`localhost`) && Path(`/2015-03-31/functions/{$functionAwsCallName}:active/invocations`)",
                    "traefik.http.middlewares.{$serviceName}-replacepath.replacepath.path=/2015-03-31/functions/function/invocations",
                    "traefik.http.routers.{$serviceName}.middlewares={$serviceName}-replacepath",
                    "traefik.http.services.{$serviceName}.loadbalancer.server.port=8080",
                ],
            ];

            if ($functionLayersPath) {
                $services[$serviceName]['volumes'][] = $functionLayersPath;
            }
        }

        return $services;
    }

    /**
     * Get function required Lambda layers from AWS cloud.
     */
    protected function grabFunctionRequiredLayers(string $basePath, string $functionClass, array $arns): string
    {
        // We need to set environment any value rather than "local"
        // to be able to grab layers from AWS cloud.
        config(['sidecar.env' => 'deploying']);

        $zipArchive = new ZipArchive();

        $functionLayerDirectory = "{$basePath}/layers/{$functionClass}";
        $this->filesystem->ensureDirectoryExists($functionLayerDirectory);

        $progressBar = $this->getOutput()->createProgressBar(count($arns));
        $progressBar->start();

        foreach ($arns as $arn) {
            $layerDirectoryName = Str::of($arn)->afterLast(':layer:')->replaceLast(':', '/');
            $layerZipFileName = $layerDirectoryName->afterLast('/')->append('.zip')->value();
            $layerDirectoryName = $layerDirectoryName->value();

            $layerTmpDirectoryPath = storage_path("tmp/layers/{$layerDirectoryName}");
            $this->filesystem->ensureDirectoryExists($layerTmpDirectoryPath);
            $layerTmpFilePath = "{$layerTmpDirectoryPath}/{$layerZipFileName}";

            if (! $this->filesystem->exists($layerTmpFilePath)) {
                $layerData = app(LambdaClient::class)->getLayerVersionByArn(['Arn' => $arn])->toArray();
        
                $layerLocation = data_get($layerData, 'Content.Location');
    
                if (! $layerLocation || ! $this->filesystem->copy($layerLocation, $layerTmpFilePath)) {
                    $this->warn("Cannot download layer for function '{$functionClass}'.");
    
                    continue;
                }
            }

            $zipArchive->open($layerTmpFilePath);
            $zipArchive->extractTo($functionLayerDirectory);
            $zipArchive->close();

            $progressBar->advance();
        }

        $progressBar->clear();

        config(['sidecar.env' => 'local']);

        return Str::of($functionLayerDirectory)
            ->replaceFirst($basePath, '.')
            ->append(':/opt:ro')
            ->value();
    }

    /**
     * Write Docker compose file functions as services and base HTTP proxy.
     */
    protected function writeComposeFile(string $basePath, array $services): bool
    {
        return (bool) $this->filesystem->put("{$basePath}/docker-compose.yml", Yaml::dump([
            'version' => '3',
            'services' => array_merge([
                'traefik' => [
                    'container_name' => 'sidecar_local',
                    'image' => 'traefik:v2.4',
                    'deploy' => [
                        'restart_policy' => [
                            'condition' => 'unless-stopped',
                        ],
                    ],
                    'ports' => [
                        config('sidecar-local.port', 6000).":80",
                        "8080:8080",
                    ],
                    'command' => [
                        "--api.insecure=true",
                        "--providers.docker=true",
                        "--providers.docker.exposedbydefault=false",
                        "--entryPoints.web.address=:80",
                    ],
                    'volumes' => [
                        "/var/run/docker.sock:/var/run/docker.sock",
                    ],
                ],
            ], $services),
        ], 99, 2));
    }

    /**
     * Run compose function services in Docker.
     */
    protected function runInDocker(string $workdir): int
    {
        $result = Process::path($workdir)->run('docker compose up -d');

        if ($result->failed()) {
            $this->error($result->errorOutput());

            return $result->exitCode();
        }

        $this->info('Docker functions services running in the background.');

        return 0;
    }

    /**
     * Stop running functions services in Docker.
     */
    protected function stopDocker(string $workdir): int
    {
        $result = Process::path($workdir)->run('docker compose stop');

        if ($result->failed()) {
            $this->error($result->errorOutput());

            return $result->exitCode();
        }

        $this->info('Docker functions services stopped successfully.');

        return 0;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['run', null, InputOption::VALUE_NONE, 'Run created services in Docker'],
            ['path', 'o', InputOption::VALUE_OPTIONAL, 'Relative path to the root where to write resulted docker-compose.yml file', 'resources/sidecar'],
            ['stop', null, InputOption::VALUE_NONE, 'Stop all Docker services previously created using this command on the path'],
        ];
    }
}
