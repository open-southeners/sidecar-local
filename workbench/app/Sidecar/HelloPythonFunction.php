<?php

namespace Workbench\App\Sidecar;

use Hammerstone\Sidecar\LambdaFunction;
use Hammerstone\Sidecar\Package;

class HelloPythonFunction extends LambdaFunction
{
    public function name()
    {
        return 'HelloPythonFunction';
    }

    public function runtime()
    {
        return 'python3.12';
    }

    public function handler()
    {
        return 'app.handler';
    }

    public function package()
    {
        return Package::make()
            ->setBasePath(workbench_path('resources/sidecar/'.class_basename(self::class)))
            ->include([
                'app.py',
                'requirements.txt',
            ]);
    }
}
