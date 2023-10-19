<?php

namespace OpenSoutheners\SidecarLocal;

use Hammerstone\Sidecar\Contracts\AwsClientConfiguration;
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
            $this->publishes([
                __DIR__.'/../config/sidecar-local.php' => config_path('sidecar-local.php'),
            ], 'config');

            $this->commands([DeployLocal::class]);
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if (config('sidecar.env') === 'local') {
            $this->app->bind(AwsClientConfiguration::class, AwsLocalClientConfiguration::class);
        }
    }
}
