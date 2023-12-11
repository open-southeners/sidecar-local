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

    public function testDeployLocalGeneratesDockerComposeYamlFile()
    {
        $this->files[] = resource_path('sidecar/docker-compose.yml');

        $command = $this->artisan('sidecar:local');

        $command->expectsOutput('Docker compose file created successfully with all functions as services.');

        $command->assertExitCode(0);

        $command->run();

        $this->assertFileContains([
            'traefik:',
            'container_name: sidecar_local',
            "image: 'traefik:v2.4'",
            'deploy:',
            'restart_policy:',
            'condition: unless-stopped',
            'ports:',
            "- '6000:80'",
            "- '8080:8080'",
            'command:',
            "- '--api.insecure=true'",
            "- '--providers.docker=true'",
            "- '--providers.docker.exposedbydefault=false'",
            "- '--entryPoints.web.address=:80'",
            'volumes:',
            "- '/var/run/docker.sock:/var/run/docker.sock'",
            'test_function:',
            'container_name: TestFunction',
            "image: 'public.ecr.aws/lambda/nodejs:16-x86_64'",
            'deploy:',
            'restart_policy:',
            'condition: on-failure',
            'max_attempts: 3',
            'command: index.handler',
            'volumes:',
            "- './TestFunction:/var/task'",
            'labels:',
            '- traefik.enable=true',
            '- traefik.http.routers.test_function.entrypoints=web',
            "- 'traefik.http.routers.test_function.rule=Host(`localhost`) && Path(`/2015-03-31/functions/local-opensoutheners-sidecarlocal-tests-fixtures-testfunction:active/invocations`)'",
            '- traefik.http.middlewares.test_function-replacepath.replacepath.path=/2015-03-31/functions/function/invocations',
            '- traefik.http.routers.test_function.middlewares=test_function-replacepath',
            '- traefik.http.services.test_function.loadbalancer.server.port=8080',
            'test_arm_function:',
            'container_name: TestArmFunction',
            "image: 'public.ecr.aws/lambda/nodejs:16-arm64'",
            'deploy:',
            'restart_policy:',
            'condition: on-failure',
            'max_attempts: 3',
            'command: index.handler',
            'volumes:',
            "- './TestArmFunction:/var/task'",
            'labels:',
            '- traefik.enable=true',
            '- traefik.http.routers.test_arm_function.entrypoints=web',
            "- 'traefik.http.routers.test_arm_function.rule=Host(`localhost`) && Path(`/2015-03-31/functions/local-opensoutheners-sidecarlocal-tests-fixtures-testarmfunction:active/invocations`)'",
            '- traefik.http.middlewares.test_arm_function-replacepath.replacepath.path=/2015-03-31/functions/function/invocations',
            '- traefik.http.routers.test_arm_function.middlewares=test_arm_function-replacepath',
            '- traefik.http.services.test_arm_function.loadbalancer.server.port=8080',
        ], 'resources/sidecar/docker-compose.yml');
    }

    public function testDeployLocalWithRunOptionTriggersASubProcess()
    {
        $process = Process::fake();

        $command = $this->artisan('sidecar:local', ['--run' => true]);

        $command->expectsOutput('Docker compose file created successfully with all functions as services.');

        $command->expectsOutput('Docker functions services running in the background.');

        $command->assertExitCode(0);

        $command->run();

        $process->assertRan('docker compose up -d');
    }

    public function testDeployLocalWithStopOptionTriggersASubProcessWithoutCreatingDockerComposeFile()
    {
        $process = Process::fake();

        $command = $this->artisan('sidecar:local', ['--stop' => true]);

        $command->doesntExpectOutput('Docker compose file created successfully with all functions as services.');

        $command->expectsOutput('Docker functions services stopped successfully.');

        $command->assertExitCode(0);

        $command->run();

        $process->assertRan('docker compose stop');
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
