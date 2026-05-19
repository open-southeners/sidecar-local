<?php

return array_replace(
    require dirname(__DIR__, 2).'/config/sidecar-local.php',
    [
        'path' => 'resources/sidecar',
    ]
);
