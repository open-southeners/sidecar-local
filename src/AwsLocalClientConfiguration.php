<?php

namespace OpenSoutheners\SidecarLocal;

use Hammerstone\Sidecar\Clients\Configurations\AwsClientConfiguration;

class AwsLocalClientConfiguration extends AwsClientConfiguration
{
    public function getConfiguration()
    {
        $baseConfig = parent::getConfiguration();

        $baseConfig['endpoint'] = 'http://localhost:'.config('sidecar-local.port', 6000);

        return $baseConfig;
    }
}
