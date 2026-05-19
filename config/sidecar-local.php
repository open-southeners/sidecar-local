<?php

return [

    /**
     * Images registry from where to pull all base images needed for Lambda functions.
     *
     * Make sure this registry is public otherwise ensure you have the right permissions.
     */
    'registry' => 'public.ecr.aws/lambda',

    /**
     * The port used to call any Dockerised service function.
     *
     * Make sure this port isn't used by any other service within your computer.
     */
    'port' => 6000,

    /**
     * Host used by the local AWS Lambda client.
     *
     * Use "sidecar-docker" when calling Lambda from a Laravel Sail container.
     */
    'host' => env('SIDECAR_LOCAL_HOST', 'localhost'),

    /**
     * Base path from where Lambda functions handlers/code are stored.
     *
     * From project's root path. For e.g. resources/sidecar.
     *
     * Null will set them to be parent directory from sidecar function PHP (setBasePath).
     */
    'path' => 'resources/sidecar',

    /**
     * Docker runtime settings for local Lambda containers.
     */
    'docker' => [
        'mode' => env('SIDECAR_LOCAL_DOCKER_MODE', 'dind'),
        'host' => env('SIDECAR_LOCAL_DOCKER_HOST'),
        'project_path' => env('SIDECAR_LOCAL_PROJECT_PATH'),
        'network' => env('SIDECAR_LOCAL_DOCKER_NETWORK', 'sail'),
        'compose_project_name' => env('SIDECAR_LOCAL_COMPOSE_PROJECT_NAME', 'sidecar-local'),
        'dind_image' => env('SIDECAR_LOCAL_DIND_IMAGE', 'docker:27-dind'),
        'traefik_image' => env('SIDECAR_LOCAL_TRAEFIK_IMAGE', 'traefik:v2.4'),
    ],

];
