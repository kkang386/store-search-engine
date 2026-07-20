<?php

use Illuminate\Support\Facades\Schedule;

// Import checking runs on the scheduler (requires `schedule:run` via cron / an ECS
// scheduled task, or a `schedule:work` sidecar). withoutOverlapping() plus the job's
// ShouldBeUnique contract guard against overlapping runs.
Schedule::command('import:check-pending')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
