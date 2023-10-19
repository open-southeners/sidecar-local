<?php

namespace OpenSoutheners\SidecarLocal\Tests\Fixtures;

use Hammerstone\Sidecar\LambdaFunction;
use Hammerstone\Sidecar\Package;

class TestFunction extends LambdaFunction
{
    public function runtime()
    {
        return 'nodejs16.x';
    }

    public function handler()
    {
        return 'index.handler';
    }

    public function package()
    {
        return Package::make()
            ->setBasePath(__DIR__.'/resources/sidecar/'.class_basename(self::class))
            ->include([
                'index.js',
                'package.json',
            ]);
    }
}
