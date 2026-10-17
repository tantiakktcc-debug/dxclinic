<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

require_once __DIR__.'/../app/Console/Commands/CreateSuperadmin.php';
\Illuminate\Console\Application::starting(function ($artisan) {
    $artisan->resolve(\App\Console\Commands\CreateSuperadmin::class);
});

