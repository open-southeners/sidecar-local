<?php

namespace OpenSoutheners\SidecarLocal\Exceptions;

class SidecarIncompatibleFunctionRuntime extends \Exception
{
    public const MESSAGE = 'Runtime "%s" not supported by function "%s". Supported "%s".';
}
