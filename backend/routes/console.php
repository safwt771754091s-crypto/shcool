<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly absence alerts to guardians (المرحلة الرابعة).
Schedule::command('notifications:absence-alerts')
    ->dailyAt('07:30')
    ->withoutOverlapping();
