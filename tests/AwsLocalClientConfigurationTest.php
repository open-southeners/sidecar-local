<?php

namespace OpenSoutheners\SidecarLocal\Tests;

use Hammerstone\Sidecar\Clients\LambdaClient;

class AwsLocalClientConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['sidecar.aws_region' => 'eu-west-2']);
    }

    public function testLambdaClientGetsLocalEndpointWhenLocalEnv()
    {
        $lambdaEndpoint = app(LambdaClient::class)->getEndpoint();

        $this->assertEquals('http://localhost:6000', $lambdaEndpoint);
    }

    public function testLambdaClientGetsOtherEndpointWhenNonLocalEnv()
    {
        config(['sidecar.env' => 'production']);

        $lambdaEndpoint = app(LambdaClient::class)->getEndpoint();

        $this->assertNotEquals('http://localhost:6000', $lambdaEndpoint);
    }
}
