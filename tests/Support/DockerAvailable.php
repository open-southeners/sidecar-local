<?php

namespace OpenSoutheners\SidecarLocal\Tests\Support;

use Illuminate\Support\Facades\Process;

class DockerAvailable
{
    public static function check(): bool
    {
        return Process::run('docker version')->successful()
            && Process::run('docker compose version')->successful();
    }
}
