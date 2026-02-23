<?php

use App\Models\School;
use App\Services\ReminderService;
use App\Services\RiskScoreService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Scheduled Tasks — EDU PAY PRO
|--------------------------------------------------------------------------
*/

// ── Planifier les nouvelles relances chaque nuit à 3h ─────────────────────
Schedule::call(function () {
    $service = app(ReminderService::class);
    School::where('is_active', true)->each(function (School $school) use ($service) {
        $service->scheduleForSchool($school);
    });
})->dailyAt('03:00')->name('schedule-reminders')->withoutOverlapping();

// ── Dispatcher les relances dues (toutes les heures 7h–20h) ───────────────
Schedule::call(function () {
    $dispatched = app(ReminderService::class)->dispatchDue();
    if ($dispatched > 0) \Illuminate\Support\Facades\Log::info("Dispatched {$dispatched} reminders.");
})->cron('0 7-20 * * *')->name('dispatch-reminders')->withoutOverlapping();

// ── Recalcul des scores de risque (toutes les nuits à 2h) ─────────────────
Schedule::call(function () {
    $service = app(RiskScoreService::class);
    School::where('is_active', true)->each(fn(School $s) => $service->computeForSchool($s));
})->dailyAt('02:00')->name('compute-risk-scores')->withoutOverlapping();

// ── Nettoyage des tokens expirés (hebdomadaire) ────────────────────────────
Schedule::command('sanctum:prune-expired --hours=720')->weekly();
