<?php

namespace OpenSoutheners\SidecarLocal;

use Hammerstone\Sidecar\Clients\LambdaClient;
use Hammerstone\Sidecar\LambdaFunction;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use OpenSoutheners\SidecarLocal\Exceptions\SidecarIncompatibleFunctionArchitecture;
use OpenSoutheners\SidecarLocal\Exceptions\SidecarIncompatibleFunctionRuntime;
use Symfony\Component\Console\Helper\ProgressBar;
use ZipArchive;

class SidecarLayers
{
    public function __construct(
        protected LambdaClient $client,
        protected Filesystem $filesystem
    ) {
        //
    }

    /**
     * Fetch function's required layers then return volume mapping otherwise false.
     */
    public function fetch(LambdaFunction $function, array $arns, ProgressBar $progressBar): string|false
    {
        if (count($arns) === 0) {
            return false;
        }

        $zipArchive = new ZipArchive();

        /** @var string $basePath */
        $basePath = config('sidecar-local.path') ?? $function->package()->getBasePath();
        $functionLayerDirectory = "{$basePath}/../layers/{$function->name()}";

        $this->filesystem->ensureDirectoryExists($functionLayerDirectory);

        $progressBar->start();

        foreach ($arns as $arn) {
            $layerTmpFilePath = $this->getLayerTmpPath($arn);

            $layerData = $this->checkArchitecture($function, $this->fetchByArn($arn));

            $layerLocation = data_get($layerData, 'Content.Location');

            if (! $layerLocation || ! is_string($layerLocation) || ! $this->filesystem->copy($layerLocation, $layerTmpFilePath)) {
                throw new \Exception("Cannot download layer for function '{$function->name()}'.");
            }

            $zipArchive->open($layerTmpFilePath);
            $zipArchive->extractTo($functionLayerDirectory);
            $zipArchive->close();

            $progressBar->advance();
        }

        $progressBar->clear();

        return Str::of($functionLayerDirectory)
            ->replaceFirst($basePath, '.')
            ->append(':/opt:ro')
            ->value();
    }

    /**
     * Get temporary path for layer artifacts (files).
     */
    protected function getLayerTmpPath(string $arn): string
    {
        $layerDirectoryName = Str::of($arn)->afterLast(':layer:')->replaceLast(':', '/');
        $layerZipFileName = $layerDirectoryName->afterLast('/')->append('.zip')->value();
        $layerDirectoryName = $layerDirectoryName->value();

        $layerTmpDirectoryPath = sys_get_temp_dir()."/SidecarLocal/layers/{$layerDirectoryName}";

        $this->filesystem->ensureDirectoryExists($layerTmpDirectoryPath);

        return "{$layerTmpDirectoryPath}/{$layerZipFileName}";
    }

    /**
     * Fetch layer by ARN string.
     */
    protected function fetchByArn(string $arn): array
    {
        return $this->client->getLayerVersionByArn(['Arn' => $arn])->toArray();
    }

    /**
     * Check function runtime is within layer compatible runtime list.
     */
    protected function checkRuntime(LambdaFunction $function, array $layer): array
    {
        if (! isset($layer['CompatibleRuntimes'])) {
            return $layer;
        }

        if (! in_array($function->runtime(), $layer['CompatibleRuntimes'])) {
            throw new SidecarIncompatibleFunctionRuntime(
                sprintf(
                    SidecarIncompatibleFunctionRuntime::MESSAGE,
                    $function->architecture(),
                    $function->name(),
                    implode(', ', $layer['CompatibleRuntimes'])
                )
            );
        }

        return $layer;
    }

    /**
     * Check function architecture is within supported layer architecture list.
     */
    protected function checkArchitecture(LambdaFunction $function, array $layer): array
    {
        if (! isset($layer['CompatibleArchitectures'])) {
            return $layer;
        }

        if (! in_array($function->architecture(), $layer['CompatibleArchitectures'])) {
            throw new SidecarIncompatibleFunctionArchitecture(
                sprintf(
                    SidecarIncompatibleFunctionArchitecture::MESSAGE,
                    $function->architecture(),
                    $function->name(),
                    implode(', ', $layer['CompatibleArchitectures'])
                )
            );
        }

        return $layer;
    }
}
