<?php

namespace OpenSoutheners\SidecarLocal;

use InvalidArgumentException;

class RuntimeImageResolver
{
    /**
     * @var array<string, string>
     */
    protected array $runtimeImages = [
        'nodejs16.x' => 'nodejs:16',
        'nodejs18.x' => 'nodejs:18',
        'nodejs20.x' => 'nodejs:20',
        'nodejs22.x' => 'nodejs:22',
        'python3.10' => 'python:3.10',
        'python3.11' => 'python:3.11',
        'python3.12' => 'python:3.12',
        'python3.13' => 'python:3.13',
    ];

    public function resolve(string $runtime, string $architecture): string
    {
        $image = $this->runtimeImages[$runtime] ?? null;

        if ($image === null) {
            throw new InvalidArgumentException("Unsupported Lambda runtime [{$runtime}] for local Sidecar Docker images.");
        }

        $registry = config('sidecar-local.registry', 'public.ecr.aws/lambda');
        $registry = is_string($registry) ? $registry : 'public.ecr.aws/lambda';

        return "{$registry}/{$image}-{$architecture}";
    }
}
