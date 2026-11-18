<?php

use App\Console\Commands\SendAppointmentRemindersCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Appointment reminders
|--------------------------------------------------------------------------
|
| Produção: cron `* * * * * php artisan schedule:run`
| Desenvolvimento: `php artisan schedule:work` ou `php artisan appointments:send-reminders`
|
*/
Schedule::command(SendAppointmentRemindersCommand::class)->everyTenMinutes();
Schedule::command(\App\Console\Commands\SyncGoogleCalendarAppointmentsCommand::class)->everyFifteenMinutes();
