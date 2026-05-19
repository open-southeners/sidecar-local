<?php

namespace OpenSoutheners\SidecarLocal\Tests;

use InvalidArgumentException;
use OpenSoutheners\SidecarLocal\RuntimeImageResolver;
use PHPUnit\Framework\Attributes\DataProvider;

class RuntimeImageResolverTest extends TestCase
{
    #[DataProvider('supportedNodeRuntimes')]
    public function testItResolvesSupportedNodeRuntimes(string $runtime, string $architecture, string $expected)
    {
        $resolver = new RuntimeImageResolver;

        $this->assertSame($expected, $resolver->resolve($runtime, $architecture));
    }

    #[DataProvider('supportedPythonRuntimes')]
    public function testItResolvesSupportedPythonRuntimesUsingConfiguredRegistry(string $runtime, string $architecture, string $expected)
    {
        config(['sidecar-local.registry' => 'example.registry/aws-lambda']);

        $resolver = new RuntimeImageResolver;

        $this->assertSame($expected, $resolver->resolve($runtime, $architecture));
    }

    public function testItThrowsForUnsupportedRuntime()
    {
        $resolver = new RuntimeImageResolver;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported Lambda runtime [ruby3.3] for local Sidecar Docker images.');

        $resolver->resolve('ruby3.3', 'x86_64');
    }

    public static function supportedNodeRuntimes(): array
    {
        return [
            ['nodejs16.x', 'x86_64', 'public.ecr.aws/lambda/nodejs:16-x86_64'],
            ['nodejs18.x', 'arm64', 'public.ecr.aws/lambda/nodejs:18-arm64'],
            ['nodejs20.x', 'x86_64', 'public.ecr.aws/lambda/nodejs:20-x86_64'],
            ['nodejs22.x', 'arm64', 'public.ecr.aws/lambda/nodejs:22-arm64'],
        ];
    }

    public static function supportedPythonRuntimes(): array
    {
        return [
            ['python3.10', 'x86_64', 'example.registry/aws-lambda/python:3.10-x86_64'],
            ['python3.11', 'arm64', 'example.registry/aws-lambda/python:3.11-arm64'],
            ['python3.12', 'x86_64', 'example.registry/aws-lambda/python:3.12-x86_64'],
            ['python3.13', 'arm64', 'example.registry/aws-lambda/python:3.13-arm64'],
        ];
    }
}
