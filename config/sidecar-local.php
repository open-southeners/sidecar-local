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
     * Base path from where Lambda functions handlers/code are stored.
     *
     * From project's root path. For e.g. resources/sidecar.
     *
     * Null will set them to be parent directory from sidecar function PHP (setBasePath).
     */
    'path' => 'resources/sidecar',

];
