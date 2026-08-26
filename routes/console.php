<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mantém séries recorrentes preenchidas até o horizonte de 12 meses
Schedule::command('duofund:extend-recurrences')->monthlyOn(1, '03:00');
