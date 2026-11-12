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
$ledgerCheck = Schedule::command('ledger:verify')
    ->dailyAt('01:30')
    ->timezone('Asia/Karachi')
    ->withoutOverlapping()
    ->onOneServer();

// A ledger that disagrees is e-mailed to operations straight away (Week 12).
if (is_string(config('donation.ops.alert_email')) && config('donation.ops.alert_email') !== '') {
    $ledgerCheck->emailOutputOnFailure((string) config('donation.ops.alert_email'));
}

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

// Week 11: unpaid Qurbani bookings stop showing as waiting once their hold runs out.
Schedule::command('qurbani:expire')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

/*
| Operations (Week 12 Day 4)
*/

// Backups: database + private files (receipts, documents) + .env, encrypted, to every disk in
// BACKUP_DISKS (one off-site). Old copies are thinned out first; problems are e-mailed.
Schedule::command('backup:clean')
    ->dailyAt('00:45')
    ->timezone('Asia/Karachi')
    ->onOneServer();

Schedule::command('backup:run')
    ->dailyAt('01:00')
    ->timezone('Asia/Karachi')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('backup:monitor')
    ->dailyAt('08:45')
    ->timezone('Asia/Karachi')
    ->onOneServer();

// Horizon's charts (jobs per minute, wait times).
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Is everything running? E-mails problems; pings the heartbeat URL only when all is well.
$heartbeat = Schedule::command('ops:health')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

if (is_string(config('donation.ops.heartbeat_url')) && config('donation.ops.heartbeat_url') !== '') {
    $heartbeat->pingOnSuccess((string) config('donation.ops.heartbeat_url'));
}
