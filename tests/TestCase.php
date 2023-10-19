<?php

namespace OpenSoutheners\SidecarLocal\Tests;

use OpenSoutheners\SidecarLocal\Tests\Fixtures\TestFunction;
use Orchestra\Testbench\TestCase as Orchestra;
use OpenSoutheners\SidecarLocal\ServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        $app['config']->set('sidecar', [
            'env' => 'local',
            'functions' => [
                TestFunction::class,
            ],
        ]);

        $app['config']->set('sidecar-local', include_once __DIR__.'/../config/sidecar-local.php');
    }
}
