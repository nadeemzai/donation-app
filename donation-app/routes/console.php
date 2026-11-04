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

// FR-FIN-01: prove every balance still matches the ledger (read-only check).
Schedule::command('ledger:verify')
    ->dailyAt('01:30')
    ->timezone('Asia/Karachi')
    ->withoutOverlapping()
    ->onOneServer();

// FR-PAY-02: settle gateway payments the donor left unfinished (status inquiry, then expiry).
Schedule::command('payments:check-stale')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Week 7: remove mobile app tokens that expired more than a day ago.
Schedule::command('sanctum:prune-expired --hours=24')
    ->dailyAt('02:15')
    ->timezone('Asia/Karachi')
    ->onOneServer();

// Week 8: pledge reminders with a pay link, at a reasonable hour in Pakistan.
Schedule::command('pledges:remind')
    ->dailyAt('09:10')
    ->timezone('Asia/Karachi')
    ->withoutOverlapping()
    ->onOneServer();

// Week 10: project impact updates go to linked donors once their publish time has come.
Schedule::command('impact:notify')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer();
