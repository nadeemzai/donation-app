<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| Production needs one cron entry: * * * * * php artisan schedule:run
| Locally: php artisan schedule:work
*/

// BR-09: close campaigns after their last day (Pakistan time).
Schedule::command('campaigns:close-expired')
    ->dailyAt('00:05')
    ->timezone('Asia/Karachi')
    ->withoutOverlapping()
    ->onOneServer();
