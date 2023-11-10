<?php

namespace OpenSoutheners\SidecarLocal\Tests;

use Hammerstone\Sidecar\Providers\SidecarServiceProvider;
use OpenSoutheners\SidecarLocal\ServiceProvider;
use OpenSoutheners\SidecarLocal\Tests\Fixtures\TestArmFunction;
use OpenSoutheners\SidecarLocal\Tests\Fixtures\TestFunction;
use Orchestra\Testbench\TestCase as Orchestra;

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
            SidecarServiceProvider::class,
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
        $app['config']->set('sidecar', include_once __DIR__.'/../vendor/hammerstone/sidecar/config/sidecar.php');

        $app['config']->set('sidecar.functions', [TestFunction::class, TestArmFunction::class]);
        $app['config']->set('sidecar.env', 'local');

        $app['config']->set('sidecar-local', include_once __DIR__.'/../config/sidecar-local.php');
    }
}
