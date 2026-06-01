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

// ── Monthly report + optional Etica statement ─────────────────────────────────
// Runs daily at 08:00; self-gates per user's monthly_day preference.
// Users with an Etica savings account automatically receive their statement
// as a PDF attachment — no separate command needed.
Schedule::command('reports:send-monthly')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onSuccess(fn() => \Log::info('Monthly reports sent successfully'))
    ->onFailure(fn() => \Log::error('Monthly reports failed'));
