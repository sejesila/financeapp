<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reports:send-annual')
    ->hourly()
    ->withoutOverlapping()
    ->onSuccess(fn() => \Log::info('Annual reports sent successfully'))
    ->onFailure(fn() => \Log::error('Annual reports failed'));

Schedule::command('reports:send-monthly')
    ->hourly()
    ->withoutOverlapping()
    ->onSuccess(fn() => \Log::info('Monthly reports sent successfully'))
    ->onFailure(fn() => \Log::error('Monthly reports failed'));
