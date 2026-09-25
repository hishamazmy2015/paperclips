<?php

use Illuminate\Support\Facades\Schedule;

// Laravel scheduler (runs in the `scheduler` container, spec §5).
Schedule::command('platform:purge')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('platform:events:partitions')->monthlyOn(25, '03:00');
