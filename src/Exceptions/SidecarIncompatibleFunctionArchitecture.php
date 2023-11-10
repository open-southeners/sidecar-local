<?php

namespace OpenSoutheners\SidecarLocal\Exceptions;

class SidecarIncompatibleFunctionArchitecture extends \Exception
{
    public const MESSAGE = 'Architecture "%s" not supported by function "%s". Supported "%s".';
}
