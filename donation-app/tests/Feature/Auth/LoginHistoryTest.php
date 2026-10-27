<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Donor;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// FR-ACC-04 — every staff login attempt is recorded

it('records a successful staff login and updates last login', function () {
    $user = User::factory()->create(['email' => 'staff@example.org']);

    Auth::guard('web')->attempt(['email' => 'staff@example.org', 'password' => 'password']);

    $entry = LoginHistory::sole();

    expect($entry->successful)->toBeTrue()
        ->and($entry->user_id)->toBe($user->id)
        ->and($entry->ip_address)->toBe('127.0.0.1')
        ->and($user->fresh()->last_login_at)->not->toBeNull()
        ->and($user->fresh()->last_login_ip)->toBe('127.0.0.1');
});

it('records a wrong password against the user', function () {
    $user = User::factory()->create(['email' => 'staff@example.org']);

    Auth::guard('web')->attempt(['email' => 'staff@example.org', 'password' => 'wrong']);

    expect(LoginHistory::sole())
        ->successful->toBeFalse()
        ->user_id->toBe($user->id)
        ->failure_reason->toBe('wrong_password');
});

it('records an unknown email without a user', function () {
    Auth::guard('web')->attempt(['email' => 'nobody@example.org', 'password' => 'whatever']);

    expect(LoginHistory::sole())
        ->user_id->toBeNull()
        ->email->toBe('nobody@example.org')
        ->failure_reason->toBe('unknown_email');
});

it('records an inactive account as a failed login and leaves last login unchanged', function () {
    $user = User::factory()->inactive()->create(['email' => 'old@example.org']);

    Auth::guard('web')->attempt(['email' => 'old@example.org', 'password' => 'password']);

    expect(LoginHistory::sole())
        ->successful->toBeFalse()
        ->failure_reason->toBe('inactive_account')
        ->and($user->fresh()->last_login_at)->toBeNull();
});

it('does not record donor logins in staff history', function () {
    Donor::factory()->create(['email' => 'donor@example.org']);

    Auth::guard('donor')->attempt(['email' => 'donor@example.org', 'password' => 'password']);

    expect(LoginHistory::count())->toBe(0);
});

it('shows login history to audit roles only', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/login-histories')->assertStatus($status);
})->with([
    'super admin' => [Role::SuperAdmin, 200],
    'auditor' => [Role::Auditor, 200],
    'trustee' => [Role::Trustee, 200],
    'manager' => [Role::Manager, 200],
    'finance' => [Role::Finance, 403],
    'field officer' => [Role::FieldOfficer, 403],
]);
