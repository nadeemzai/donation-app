<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\OpsHealth;
use App\Support\SecurityChecks;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;

// W12-D4 — production operations: schedule, ops:health alerts, backups, deploys.

function scheduled(string $command): ?Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $e): bool => str_contains((string) $e->command, $command));
}

/** Messages the "array" mailer (phpunit.xml) has sent. */
function sentMails(): int
{
    return count(Mail::mailer()->getSymfonyTransport()->messages());
}

it('schedules backups, their check, Horizon snapshots and the health check', function () {
    expect(scheduled('backup:run')?->expression)->toBe('0 1 * * *')
        ->and(scheduled('backup:run')?->timezone)->toBe('Asia/Karachi')
        ->and(scheduled('backup:clean'))->not->toBeNull()
        ->and(scheduled('backup:monitor'))->not->toBeNull()
        ->and(scheduled('horizon:snapshot')?->expression)->toBe('*/5 * * * *')
        ->and(scheduled('ops:health')?->expression)->toBe('*/10 * * * *');
});

it('is quiet when all is well', function () {
    expect(OpsHealth::problems())->toBe([]);

    $this->artisan('ops:health')->assertSuccessful();
});

it('reports a payment stuck for hours and one waiting for review — e-mailed once, not every 10 minutes', function () {
    config()->set('donation.ops.alert_email', 'ops@example.org');

    $stuck = Payment::factory()->create(['initiated_at' => now()->subHours(3)]);
    $review = Payment::factory()->create();
    $review->forceFill(['status' => PaymentStatus::NeedsReview])->save();

    expect(OpsHealth::problems())->toHaveCount(2);

    $this->artisan('ops:health')->assertFailed();
    $this->artisan('ops:health')->assertFailed();

    expect(sentMails())->toBe(1);
});

it('keeps answering payment callbacks while a deploy shows the maintenance page', function () {
    // In the cache (per test process), not a file shared with tests running in parallel.
    config()->set(['app.maintenance.driver' => 'cache', 'app.maintenance.store' => 'array']);
    $this->artisan('down')->assertSuccessful();

    try {
        $this->get('/')->assertStatus(503);
        $this->post('/payments/jazzcash/notify', ['pp_TxnRefNo' => 'T1'])->assertStatus(200);
        $this->get('/up')->assertOk();
    } finally {
        $this->artisan('up');
    }
});

it('refuses to go live with backups only on this server or unencrypted', function () {
    config()->set(['backup.backup.destination.disks' => ['local'], 'backup.backup.password' => null]);
    $s = collect(SecurityChecks::run())->mapWithKeys(fn (array $c): array => [$c['check'] => $c['status']]);

    expect($s['Backups also go off-site'])->toBe(SecurityChecks::FAIL)
        ->and($s['Backups are encrypted'])->toBe(SecurityChecks::FAIL);

    config()->set(['backup.backup.destination.disks' => ['local', 'backups'], 'backup.backup.password' => 'long-secret']);
    $s = collect(SecurityChecks::run())->mapWithKeys(fn (array $c): array => [$c['check'] => $c['status']]);

    expect($s['Backups also go off-site'])->toBe(SecurityChecks::PASS)
        ->and($s['Backups are encrypted'])->toBe(SecurityChecks::PASS);
});
