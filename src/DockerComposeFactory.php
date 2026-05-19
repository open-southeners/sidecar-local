<?php

namespace OpenSoutheners\SidecarLocal;

use Hammerstone\Sidecar\LambdaFunction;
use Illuminate\Support\Str;

class DockerComposeFactory
{
    public function __construct(protected RuntimeImageResolver $runtimeImageResolver)
    {
    }

    /**
     * @param  array<string, mixed>  $services
     * @return array<string, mixed>
     */
    public function hostCompose(array $services): array
    {
        return [
            'version' => '3',
            'services' => array_merge([
                'traefik' => $this->traefikService([
                    '/var/run/docker.sock:/var/run/docker.sock',
                ]),
            ], $services),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dindBootstrapCompose(string $projectPath, bool $sail = false): array
    {
        $port = $this->configString('sidecar-local.port', '6000');

        $service = [
            'container_name' => 'sidecar_docker',
            'image' => $this->configString('sidecar-local.docker.dind_image', 'docker:27-dind'),
            'privileged' => true,
            'environment' => [
                'DOCKER_TLS_CERTDIR=',
            ],
            'ports' => [
                '2375:2375',
                "{$port}:{$port}",
                '8080:8080',
            ],
            'command' => [
                '--tls=false',
                '--host=tcp://0.0.0.0:2375',
                '--host=unix:///var/run/docker.sock',
            ],
            'volumes' => [
                "{$projectPath}:{$projectPath}",
                'sidecar_docker_data:/var/lib/docker',
            ],
        ];

        if ($sail) {
            $network = $this->configString('sidecar-local.docker.network', 'sail');
            $service['networks'] = [
                $network => [
                    'aliases' => ['sidecar-docker'],
                ],
            ];
        }

        return [
            'version' => '3',
            'services' => [
                'sidecar-docker' => $service,
            ],
            'volumes' => [
                'sidecar_docker_data' => null,
            ],
            ...($sail ? [
                'networks' => [
                    $this->configString('sidecar-local.docker.network', 'sail') => [
                        'external' => true,
                    ],
                ],
            ] : []),
        ];
    }

    /**
     * @param  array<string, mixed>  $services
     * @return array<string, mixed>
     */
    public function dindRuntimeCompose(array $services): array
    {
        return [
            'version' => '3',
            'services' => array_merge([
                'traefik' => $this->traefikService([
                    '/var/run/docker.sock:/var/run/docker.sock',
                ]),
            ], $services),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function lambdaService(LambdaFunction $lambdaFunction, ?string $functionLayersPath = null): array
    {
        $functionClassName = class_basename(get_class($lambdaFunction));
        $serviceName = $this->lambdaServiceName($lambdaFunction);
        $functionAwsCallName = $lambdaFunction->nameWithPrefix();

        $service = [
            'container_name' => $functionClassName,
            'image' => $this->runtimeImageResolver->resolve($lambdaFunction->runtime(), $lambdaFunction->architecture()),
            'deploy' => [
                'restart_policy' => [
                    'condition' => 'on-failure',
                    'max_attempts' => 3,
                ],
            ],
            'command' => $lambdaFunction->handler(),
            'volumes' => ["./{$functionClassName}:/var/task"],
            'labels' => [
                'traefik.enable=true',
                "traefik.http.routers.{$serviceName}.entrypoints=web",
                "traefik.http.routers.{$serviceName}.rule=Host(`localhost`) && Path(`/2015-03-31/functions/{$functionAwsCallName}:active/invocations`)",
                "traefik.http.middlewares.{$serviceName}-replacepath.replacepath.path=/2015-03-31/functions/function/invocations",
                "traefik.http.routers.{$serviceName}.middlewares={$serviceName}-replacepath",
                "traefik.http.services.{$serviceName}.loadbalancer.server.port=8080",
            ],
        ];

        if ($functionLayersPath) {
            $service['volumes'][] = $functionLayersPath;
        }

        return $service;
    }

    public function lambdaServiceName(LambdaFunction $lambdaFunction): string
    {
        return Str::snake(Str::remove('-', class_basename(get_class($lambdaFunction))));
    }

    /**
     * @param  array<int, string>  $volumes
     * @return array<string, mixed>
     */
    protected function traefikService(array $volumes = []): array
    {
        return [
            'container_name' => 'sidecar_local',
            'image' => $this->configString('sidecar-local.docker.traefik_image', 'traefik:v2.4'),
            'deploy' => [
                'restart_policy' => [
                    'condition' => 'unless-stopped',
                ],
            ],
            'ports' => [
                $this->configString('sidecar-local.port', '6000').':80',
                '8080:8080',
            ],
            'command' => [
                '--api.insecure=true',
                '--providers.docker=true',
                '--providers.docker.exposedbydefault=false',
                '--entryPoints.web.address=:80',
            ],
            ...($volumes === [] ? [] : ['volumes' => $volumes]),
        ];
    }

    protected function configString(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }
}
