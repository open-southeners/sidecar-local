<?php

namespace OpenSoutheners\SidecarLocal;

use Hammerstone\Sidecar\Contracts\AwsClientConfiguration as AwsClientConfigurationContract;
use Hammerstone\Sidecar\Clients\Configurations\AwsClientConfiguration;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use OpenSoutheners\SidecarLocal\Commands\DeployLocal;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([DeployLocal::class]);
        }

        $this->publishes([
            __DIR__.'/../config/sidecar-local.php' => config_path('sidecar-local.php'),
        ], 'config');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(AwsClientConfigurationContract::class, function (Application $app) {
            return $app->make('config')->get('sidecar.env') === 'local'
                ? $app->make(AwsLocalClientConfiguration::class)
                : $app->make(AwsClientConfiguration::class);
        });
    }
}
