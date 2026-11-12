<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\User;
use App\Services\SettlementService;
use Illuminate\Support\Facades\Storage;

// W12-D3 — test accounts for staff testing on staging.

it('creates one active account per role, ready for two-factor set-up', function () {
    $this->artisan('uat:accounts', ['--domain' => 'imdaad.test'])->assertSuccessful();

    expect(User::query()->where('email', 'like', 'uat.%@imdaad.test')->count())->toBe(count(Role::cases()));

    $finance = User::query()->where('email', 'uat.finance@imdaad.test')->sole();
    expect($finance->hasRole(Role::Finance->value))->toBeTrue()
        ->and($finance->is_active)->toBeTrue()
        ->and($finance->hasMfaEnabled())->toBeFalse();
});

it('gives new passwords when run again', function () {
    $this->artisan('uat:accounts')->assertSuccessful();
    $before = User::query()->where('email', 'uat.manager@example.org')->value('password');

    $this->artisan('uat:accounts')->assertSuccessful();

    expect(User::query()->where('email', 'uat.manager@example.org')->value('password'))->not->toBe($before)
        ->and(User::query()->where('email', 'like', 'uat.%')->count())->toBe(count(Role::cases()));
});

it('switches the test accounts off afterwards', function () {
    $this->artisan('uat:accounts')->assertSuccessful();
    $this->artisan('uat:accounts', ['--disable' => true])->assertSuccessful();

    expect(User::query()->where('email', 'like', 'uat.%')->where('is_active', true)->exists())->toBeFalse();
});

it('never runs in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('uat:accounts')->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('adds demo data on staging but never in production', function () {
    app()->detectEnvironment(fn (): string => 'production');
    $this->artisan('demo:seed', ['--donors' => 2])->assertFailed();

    app()->detectEnvironment(fn (): string => 'staging');
    $this->artisan('demo:seed', ['--donors' => 2])->assertSuccessful();
});

it('writes a sample settlement file with one wrong amount and one unknown row', function () {
    Storage::fake('local');
    $payments = Payment::factory()->count(2)->create()->each(fn (Payment $p) => $p->forceFill([
        'status' => PaymentStatus::Succeeded, 'gateway_ref' => 'GW-'.$p->id, 'completed_at' => now(),
    ])->save());

    $this->artisan('settlements:sample')->assertSuccessful();

    $csv = Storage::disk('local')->get('uat/settlement-jazzcash-'.today()->format('Y-m-d').'.csv');
    $settlement = app(SettlementService::class)->import(PaymentMethod::JazzCash, now(), (string) $csv, 'sample.csv');

    expect($settlement->matched_count)->toBe(1)
        ->and($settlement->problem_count)->toBe(2);
});
