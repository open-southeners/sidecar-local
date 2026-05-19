<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('workbench:about', function () {
    $this->info('sidecar-local workbench');
});
