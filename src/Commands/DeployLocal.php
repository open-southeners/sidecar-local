<?php

namespace OpenSoutheners\SidecarLocal\Commands;

use Hammerstone\Sidecar\LambdaFunction;
use Hammerstone\Sidecar\Sidecar;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use OpenSoutheners\SidecarLocal\DockerComposeFactory;
use OpenSoutheners\SidecarLocal\SidecarLayers;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

class DeployLocal extends Command
{
    protected const BOOTSTRAP_COMPOSE_FILE = 'docker-compose.yml';

    protected const RUNTIME_COMPOSE_FILE = 'docker-compose.sidecar.yml';

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

    public function __construct(
        protected Filesystem $filesystem,
        protected DockerComposeFactory $composeFactory,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (config('sidecar.env') !== 'local') {
            $this->error('Make sure you have sidecar.env or app.env as "local".');

            return 2;
        }

        $path = $this->option('path');
        $fullPath = base_path(is_string($path) ? $path : 'resources/sidecar');

        $mode = $this->configString('sidecar-local.docker.mode', 'dind');
        $sail = (bool) $this->option('sail');

        if ($sail) {
            $mode = 'dind';
        }

        if ($this->option('stop')) {
            return $this->stopDocker($fullPath, $mode, $sail);
        }

        if (! $this->writeComposeFiles($fullPath, $this->buildYamlServices(), $mode, $sail)) {
            $this->error('Error writing the docker-compose.yml file.');

            return 3;
        }

        $this->info('Docker compose file created successfully with all functions as services.');

        if ($this->option('run')) {
            return $this->runInDocker($fullPath, $mode, $sail);
        }

        return 0;
    }

    /**
     * Build function services array to be written by Yaml dumper.
     *
     * @return array<string, mixed>
     */
    protected function buildYamlServices(): array
    {
        /** @var array<\Hammerstone\Sidecar\LambdaFunction> $sidecarFunctions */
        $sidecarFunctions = Sidecar::instantiatedFunctions();
        $services = [];

        foreach ($sidecarFunctions as $lambdaFunction) {
            $lambdaFunction->beforeDeployment();

            $functionLayers = array_values(array_filter($lambdaFunction->layers(), 'is_string'));
            $functionLayersPath = null;

            if (count($functionLayers) > 0) {
                $functionLayersPath = $this->grabFunctionRequiredLayers($lambdaFunction, $functionLayers);
            }

            $services[$this->composeFactory->lambdaServiceName($lambdaFunction)] = $this->composeFactory
                ->lambdaService($lambdaFunction, $functionLayersPath);
        }

        return $services;
    }

    /**
     * Get function required Lambda layers from AWS cloud.
     *
     * @param  array<int, string>  $arns
     */
    protected function grabFunctionRequiredLayers(LambdaFunction $function, array $arns): string
    {
        $progressBar = $this->getOutput()->createProgressBar(count($arns));

        // We need to set environment any value rather than "local"
        // to be able to grab layers from AWS cloud.
        config(['sidecar.env' => 'deploying']);

        $lambdaLayersVolume = app(SidecarLayers::class)->fetch($function, $arns, $progressBar);

        config(['sidecar.env' => 'local']);

        return $lambdaLayersVolume ?: '';
    }

    /**
     * Write Docker compose file functions as services and base HTTP proxy.
     *
     * @param  array<string, mixed>  $services
     */
    protected function writeComposeFiles(string $basePath, array $services, string $mode, bool $sail): bool
    {
        if ($mode === 'host') {
            return $this->writeComposeFile($basePath, self::BOOTSTRAP_COMPOSE_FILE, $this->composeFactory->hostCompose($services));
        }

        $projectPath = $this->configString('sidecar-local.docker.project_path', base_path());

        return $this->writeComposeFile($basePath, self::BOOTSTRAP_COMPOSE_FILE, $this->composeFactory->dindBootstrapCompose($projectPath, $sail))
            && $this->writeComposeFile($basePath, self::RUNTIME_COMPOSE_FILE, $this->composeFactory->dindRuntimeCompose($services));
    }

    /**
     * Run compose function services in Docker.
     */
    protected function runInDocker(string $workdir, string $mode, bool $sail): int
    {
        if ($mode === 'host') {
            return $this->runProcess(
                $workdir,
                $this->composeCommand(self::BOOTSTRAP_COMPOSE_FILE, 'up -d --remove-orphans'),
                [],
                'Docker functions services running in the background.'
            );
        }

        $bootstrapResult = $this->runProcess(
            $workdir,
            $this->composeCommand(self::BOOTSTRAP_COMPOSE_FILE, 'up -d --remove-orphans')
        );

        if ($bootstrapResult !== 0) {
            return $bootstrapResult;
        }

        $dockerHost = $this->dockerHost($sail);
        $waitResult = $this->waitForDockerDaemon($workdir, $dockerHost);

        if ($waitResult !== 0) {
            return $waitResult;
        }

        return $this->runProcess(
            $workdir,
            $this->composeCommand(self::RUNTIME_COMPOSE_FILE, 'up -d --remove-orphans', $this->runtimeComposeProjectName()),
            ['DOCKER_HOST' => $dockerHost],
            'Docker functions services running in the background.'
        );
    }

    /**
     * Stop running functions services in Docker.
     */
    protected function stopDocker(string $workdir, string $mode, bool $sail): int
    {
        if ($mode === 'host') {
            return $this->runProcess(
                $workdir,
                $this->composeCommand(self::BOOTSTRAP_COMPOSE_FILE, 'down --remove-orphans'),
                [],
                'Docker functions services stopped successfully.'
            );
        }

        $dockerHost = $this->dockerHost($sail);
        $runtimeResult = $this->runProcess(
            $workdir,
            $this->composeCommand(self::RUNTIME_COMPOSE_FILE, 'down --remove-orphans', $this->runtimeComposeProjectName()),
            ['DOCKER_HOST' => $dockerHost]
        );

        if ($runtimeResult !== 0) {
            return $runtimeResult;
        }

        return $this->runProcess(
            $workdir,
            $this->composeCommand(self::BOOTSTRAP_COMPOSE_FILE, 'down --remove-orphans'),
            [],
            'Docker functions services stopped successfully.'
        );
    }

    /**
     * @param  array<string, mixed>  $compose
     */
    protected function writeComposeFile(string $basePath, string $filename, array $compose): bool
    {
        return (bool) $this->filesystem->put("{$basePath}/{$filename}", Yaml::dump($compose, 99, 2));
    }

    /**
     * @param  array<string, string>  $env
     */
    protected function runProcess(string $workdir, string $command, array $env = [], ?string $successMessage = null): int
    {
        $pendingProcess = Process::path($workdir);

        if ($env !== []) {
            $pendingProcess = $pendingProcess->env($env);
        }

        $result = $pendingProcess->run($command);

        if ($result->failed()) {
            $this->error($result->errorOutput());

            return $result->exitCode() ?? 0;
        }

        if ($successMessage) {
            $this->info($successMessage);
        }

        return 0;
    }

    protected function waitForDockerDaemon(string $workdir, string $dockerHost): int
    {
        for ($attempt = 0; $attempt < 15; $attempt++) {
            $result = Process::path($workdir)
                ->env(['DOCKER_HOST' => $dockerHost])
                ->run('docker info');

            if ($result->successful()) {
                return 0;
            }

            sleep(1);
        }

        $this->error("Docker daemon [{$dockerHost}] was not ready.");

        return 1;
    }

    protected function composeCommand(string $file, string $arguments, ?string $projectName = null): string
    {
        $projectName ??= $this->configString('sidecar-local.docker.compose_project_name', 'sidecar-local');

        return "docker compose --project-name {$projectName} -f {$file} {$arguments}";
    }

    protected function dockerHost(bool $sail): string
    {
        $host = config('sidecar-local.docker.host');

        if (is_scalar($host) && $host !== '') {
            return (string) $host;
        }

        return $sail ? 'tcp://sidecar-docker:2375' : 'tcp://localhost:2375';
    }

    protected function runtimeComposeProjectName(): string
    {
        return $this->configString('sidecar-local.docker.compose_project_name', 'sidecar-local').'-functions';
    }

    protected function configString(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Get the console command options.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions()
    {
        return [
            ['run', null, InputOption::VALUE_NONE, 'Run created services in Docker'],
            ['path', 'o', InputOption::VALUE_OPTIONAL, 'Relative path to the root where to write resulted docker-compose.yml file', config('sidecar-local.path', 'resources/sidecar')],
            ['stop', null, InputOption::VALUE_NONE, 'Stop all Docker services previously created using this command on the path'],
            ['sail', null, InputOption::VALUE_NONE, 'Generate and run Docker in Docker services attached to a Laravel Sail network'],
        ];
    }
}
