<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule daily attendance check to mark alpha for students who haven't attended
Schedule::command('app:check-daily-attendance')->dailyAt('23:59')->description('Check daily attendance and mark alpha for students who havent attended today');
