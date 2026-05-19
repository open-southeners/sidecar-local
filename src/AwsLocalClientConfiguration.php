<?php

namespace OpenSoutheners\SidecarLocal;

use Hammerstone\Sidecar\Clients\Configurations\AwsClientConfiguration;

class AwsLocalClientConfiguration extends AwsClientConfiguration
{
    /**
     * @return array<array-key, mixed>
     */
    public function getConfiguration(): array
    {
        $baseConfig = parent::getConfiguration();
        $host = config('sidecar-local.host', 'localhost');
        $port = config('sidecar-local.port', 6000);

        $baseConfig['endpoint'] = 'http://'.(is_scalar($host) ? $host : 'localhost').':'.(is_scalar($port) ? $port : 6000);

        return $baseConfig;
    }
}
