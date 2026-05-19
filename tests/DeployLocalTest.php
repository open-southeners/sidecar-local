<?php

namespace OpenSoutheners\SidecarLocal\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Orchestra\Testbench\Concerns\InteractsWithPublishedFiles;

class DeployLocalTest extends TestCase
{
    use InteractsWithPublishedFiles;

    /**
     * @var string[]
     */
    protected $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        app(Filesystem::class)->ensureDirectoryExists(resource_path('sidecar'));
    }

    public function testDeployLocalGeneratesDockerComposeYamlFiles()
    {
        $this->files[] = resource_path('sidecar/docker-compose.yml');
        $this->files[] = resource_path('sidecar/docker-compose.sidecar.yml');

        $command = $this->artisan('sidecar:local');

        $command->expectsOutput('Docker compose file created successfully with all functions as services.');

        $command->assertExitCode(0);

        $command->run();

        $this->assertFileContains([
            'sidecar-docker:',
            'container_name: sidecar_docker',
            "image: 'docker:27-dind'",
            'privileged: true',
            'environment:',
            '- DOCKER_TLS_CERTDIR=',
            'ports:',
            "- '2375:2375'",
            "- '6000:6000'",
            "- '8080:8080'",
            'volumes:',
            "- '".base_path().':'.base_path()."'",
            "- 'sidecar_docker_data:/var/lib/docker'",
            'sidecar_docker_data: null',
        ], 'resources/sidecar/docker-compose.yml');

        $this->assertFileContains([
            'traefik:',
            'container_name: sidecar_local',
            "image: 'traefik:v2.4'",
            "- '6000:80'",
            "- '8080:8080'",
            'hello_node_function:',
            'container_name: HelloNodeFunction',
            "image: 'public.ecr.aws/lambda/nodejs:20-x86_64'",
            'command: index.handler',
            "- './HelloNodeFunction:/var/task'",
            'hello_python_function:',
            'container_name: HelloPythonFunction',
            "image: 'public.ecr.aws/lambda/python:3.12-x86_64'",
            'command: app.handler',
            "- './HelloPythonFunction:/var/task'",
        ], 'resources/sidecar/docker-compose.sidecar.yml');
    }

    public function testDeployLocalCanGenerateLegacyHostDockerComposeYamlFile()
    {
        config(['sidecar-local.docker.mode' => 'host']);

        $this->files[] = resource_path('sidecar/docker-compose.yml');

        $command = $this->artisan('sidecar:local');

        $command->expectsOutput('Docker compose file created successfully with all functions as services.');

        $command->assertExitCode(0);

        $command->run();

        $this->assertFileContains([
            'traefik:',
            'container_name: sidecar_local',
            "image: 'traefik:v2.4'",
            'volumes:',
            "- '/var/run/docker.sock:/var/run/docker.sock'",
            'hello_node_function:',
            'container_name: HelloNodeFunction',
            "image: 'public.ecr.aws/lambda/nodejs:20-x86_64'",
        ], 'resources/sidecar/docker-compose.yml');
    }

    public function testDeployLocalWithRunOptionTriggersSubProcesses()
    {
        $process = Process::fake();

        $command = $this->artisan('sidecar:local', ['--run' => true]);

        $command->expectsOutput('Docker compose file created successfully with all functions as services.');

        $command->expectsOutput('Docker functions services running in the background.');

        $command->assertExitCode(0);

        $command->run();

        $process->assertRan('docker compose --project-name sidecar-local -f docker-compose.yml up -d --remove-orphans');
        $process->assertRan('docker info');
        $process->assertRan('docker compose --project-name sidecar-local-functions -f docker-compose.sidecar.yml up -d --remove-orphans');
    }

    public function testDeployLocalWithStopOptionTriggersSubProcessesWithoutCreatingDockerComposeFile()
    {
        $process = Process::fake();

        $command = $this->artisan('sidecar:local', ['--stop' => true]);

        $command->doesntExpectOutput('Docker compose file created successfully with all functions as services.');

        $command->expectsOutput('Docker functions services stopped successfully.');

        $command->assertExitCode(0);

        $command->run();

        $process->assertRan('docker compose --project-name sidecar-local-functions -f docker-compose.sidecar.yml down --remove-orphans');
        $process->assertRan('docker compose --project-name sidecar-local -f docker-compose.yml down --remove-orphans');
    }

    public function testDeployLocalWithSailOptionUsesSailNetwork()
    {
        $this->files[] = resource_path('sidecar/docker-compose.yml');
        $this->files[] = resource_path('sidecar/docker-compose.sidecar.yml');

        $command = $this->artisan('sidecar:local', ['--sail' => true]);

        $command->expectsOutput('Docker compose file created successfully with all functions as services.');

        $command->assertExitCode(0);

        $command->run();

        $this->assertFileContains([
            'networks:',
            'sail:',
            'aliases:',
            '- sidecar-docker',
            'external: true',
        ], 'resources/sidecar/docker-compose.yml');
    }

    public function testDeployLocalWhenSidecarEnvConfigIsNotEqualLocalShowsError()
    {
        config(['sidecar.env' => 'production']);

        $process = Process::fake();

        $command = $this->artisan('sidecar:local');

        $command->doesntExpectOutput('Docker compose file created successfully with all functions as services.');

        $command->expectsOutput('Make sure you have sidecar.env or app.env as "local".');

        $command->assertExitCode(2);

        $process->assertNothingRan();
    }
}
