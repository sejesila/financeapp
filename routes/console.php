<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Annual reports — January 1st at 08:00 ────────────────────────────────────
Schedule::command('reports:send-annual')
    ->yearlyOn(1, 1, '08:00')
    ->withoutOverlapping()
    ->onSuccess(fn() => \Log::info('Annual reports sent successfully'))
    ->onFailure(fn() => \Log::error('Annual reports failed'));

// ── Combined monthly report + Etica statement ─────────────────────────────────
// Runs daily at 08:00; the command self-gates per user's monthly_day preference
Schedule::command('reports:send-monthly-with-statement')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onSuccess(fn() => \Log::info('Monthly reports + Etica statements sent successfully'))
    ->onFailure(fn() => \Log::error('Monthly reports + Etica statements failed'));

// ── Standalone Etica statements — 1st of every month at 08:00 ────────────────
// Sends to Etica users who may not have monthly_reports enabled
Schedule::command('statements:send-etica')
    ->monthlyOn(1, '08:00')
    ->withoutOverlapping()
    ->onSuccess(fn() => \Log::info('Etica statements sent successfully'))
    ->onFailure(fn() => \Log::error('Etica statements failed'));
