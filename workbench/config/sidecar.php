<?php

require_once __DIR__.'/../app/Sidecar/HelloNodeFunction.php';
require_once __DIR__.'/../app/Sidecar/HelloPythonFunction.php';

return array_replace(
    require dirname(__DIR__, 2).'/vendor/hammerstone/sidecar/config/sidecar.php',
    [
        'app_name' => 'sidecar-local-workbench',
        'env' => 'local',
        'package_base_path' => dirname(__DIR__),
        'functions' => [
            new \Workbench\App\Sidecar\HelloNodeFunction,
            new \Workbench\App\Sidecar\HelloPythonFunction,
        ],
    ]
);
