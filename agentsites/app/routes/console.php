<?php

use App\Jobs\SendOnboardingReminders;
use Illuminate\Support\Facades\Schedule;

// Laravel scheduler (runs in the `scheduler` container, spec §5).
Schedule::command('platform:purge')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('platform:events:partitions')->monthlyOn(25, '03:00');
// Abandonment reminders at +1 h / +24 h (spec §13); the job itself decides who is due.
Schedule::job(new SendOnboardingReminders)->everyFifteenMinutes()->withoutOverlapping();
