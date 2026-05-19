<?php

namespace OpenSoutheners\SidecarLocal\Tests;

use Hammerstone\Sidecar\Sidecar;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use OpenSoutheners\SidecarLocal\Tests\Support\DockerAvailable;
use PHPUnit\Framework\Attributes\Group;
use Workbench\App\Sidecar\HelloNodeFunction;

#[Group('docker')]
class DockerIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! DockerAvailable::check()) {
            $this->markTestSkipped('Docker is not available.');
        }

        app(Filesystem::class)->ensureDirectoryExists(resource_path('sidecar'));
        app(Filesystem::class)->delete([
            resource_path('sidecar/docker-compose.yml'),
            resource_path('sidecar/docker-compose.sidecar.yml'),
        ]);
    }

    protected function tearDown(): void
    {
        $this->artisan('sidecar:local', ['--stop' => true])->run();

        parent::tearDown();
    }

    public function testItInvokesNodeAndPythonFunctionsThroughDind()
    {
        $this->artisan('sidecar:local', ['--run' => true])->assertExitCode(0)->run();

        $nodeFunction = Sidecar::instantiatedFunctions([HelloNodeFunction::class])[0];

        $nodeResponse = $this->invokeFunction($nodeFunction->nameWithPrefix(), ['name' => 'Sidecar']);

        $this->assertSame('Hello, Sidecar from Node.js', $nodeResponse['message'] ?? null);

        $pythonFunction = collect(Sidecar::instantiatedFunctions())
            ->first(fn ($function) => $function instanceof \Workbench\App\Sidecar\HelloPythonFunction);

        $pythonResponse = $this->invokeFunction($pythonFunction->nameWithPrefix(), ['name' => 'Sidecar']);

        $this->assertSame('Hello, Sidecar from Python', $pythonResponse['message'] ?? null);
    }

    public function testItRemovesContainersForFunctionsThatAreNoLongerConfigured()
    {
        $this->artisan('sidecar:local', ['--run' => true])->assertExitCode(0)->run();

        config(['sidecar.functions' => [HelloNodeFunction::class]]);

        $this->artisan('sidecar:local', ['--run' => true])->assertExitCode(0)->run();

        $containers = Process::env(['DOCKER_HOST' => 'tcp://localhost:2375'])
            ->run('docker ps --format "{{.Names}}"')
            ->output();

        $this->assertStringContainsString('HelloNodeFunction', $containers);
        $this->assertStringNotContainsString('HelloPythonFunction', $containers);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function invokeFunction(string $name, array $payload): array
    {
        $url = "http://localhost:6000/2015-03-31/functions/{$name}:active/invocations";

        for ($attempt = 0; $attempt < 30; $attempt++) {
            try {
                $response = Http::timeout(2)->post($url, $payload);
            } catch (ConnectionException) {
                sleep(1);

                continue;
            }

            if ($response->successful()) {
                return $response->json();
            }

            sleep(1);
        }

        $this->fail("Function [{$name}] was not invokable through local Sidecar.");
    }
}
