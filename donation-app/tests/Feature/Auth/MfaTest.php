<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Support\Facades\DB;

// FR-ACC-01 — authenticator app required for Super Admin, Finance, Trustee

it('requires two-factor setup for the right roles only', function () {
    expect(Role::mfaRequiredValues())->toBe(['super_admin', 'finance', 'trustee']);
});

it('sends a privileged user without two-factor to their profile page', function (Role $role) {
    $this->actingAs(staffUser($role, withMfa: false))
        ->get('/admin')
        ->assertRedirectContains('/admin/profile');
})->with([Role::SuperAdmin, Role::Finance, Role::Trustee]);

it('lets a privileged user with two-factor into the panel', function (Role $role) {
    $this->actingAs(staffUser($role))->get('/admin')->assertOk();
})->with([Role::SuperAdmin, Role::Finance, Role::Trustee]);

it('does not force two-factor on other roles', function (Role $role) {
    $this->actingAs(staffUser($role, withMfa: false))->get('/admin')->assertOk();
})->with([Role::Manager, Role::CampaignManager, Role::FieldOfficer, Role::Auditor]);

it('still lets a user without two-factor open the profile page to set it up', function () {
    $this->actingAs(staffUser(Role::Finance, withMfa: false))
        ->get('/admin/profile')
        ->assertOk();
});

it('stores the authenticator secret encrypted and hides it from JSON', function () {
    $user = staffUser(Role::Finance);

    expect(DB::table('users')->where('id', $user->id)->value('app_authentication_secret'))->not->toBe('JBSWY3DPEHPK3PXP')
        ->and($user->fresh()->hasMfaEnabled())->toBeTrue()
        ->and($user->toArray())->not->toHaveKey('app_authentication_secret');
});
