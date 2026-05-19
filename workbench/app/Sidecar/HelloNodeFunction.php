<?php

namespace Workbench\App\Sidecar;

use Hammerstone\Sidecar\LambdaFunction;
use Hammerstone\Sidecar\Package;

class HelloNodeFunction extends LambdaFunction
{
    public function name()
    {
        return 'HelloNodeFunction';
    }

    public function runtime()
    {
        return 'nodejs20.x';
    }

    public function handler()
    {
        return 'index.handler';
    }

    public function package()
    {
        return Package::make()
            ->setBasePath(workbench_path('resources/sidecar/'.class_basename(self::class)))
            ->include([
                'index.js',
                'package.json',
            ]);
    }
}
