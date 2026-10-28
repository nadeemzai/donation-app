<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Pages\Settings\ManageApprovalSettings;
use App\Filament\Pages\Settings\ManageGatewaySettings;
use App\Models\Donor;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// FR-AUD-01, NFR-07 — who changed what, before → after; secrets never logged

function lastActivity(): Activity
{
    return Activity::query()->latest('id')->firstOrFail();
}

it('records who changed a staff user, with before and after values', function () {
    $admin = staffUser(Role::SuperAdmin);
    $this->actingAs($admin);

    $user = User::factory()->create(['name' => 'Old Name']);
    $user->update(['name' => 'New Name']);

    $activity = lastActivity();

    expect($activity)
        ->log_name->toBe('audit')
        ->event->toBe('updated')
        ->subject_id->toBe($user->id)
        ->causer_id->toBe($admin->id)
        ->and($activity->attribute_changes['old']['name'])->toBe('Old Name')
        ->and($activity->attribute_changes['attributes']['name'])->toBe('New Name');
});

it('records only the fields that changed', function () {
    $user = User::factory()->create();
    $user->update(['phone' => '03001234567']);

    expect(array_keys(lastActivity()->attribute_changes['attributes']))->toBe(['phone']);
});

it('never logs passwords', function () {
    $user = User::factory()->create();
    $user->update(['password' => 'a-brand-new-password']);

    $all = Activity::all()->toJson();

    expect($all)->not->toContain('password')
        ->not->toContain('a-brand-new-password');
});

it('never logs a donor\'s CNIC, NTN or password', function () {
    $donor = Donor::factory()->create(['cnic' => '35202-1234567-1', 'ntn' => '1234567-8', 'phone' => '03009998887', 'email' => 'donor@example.org', 'city' => 'Lahore']);
    $donor->update(['cnic' => '61101-7654321-9', 'city' => 'Multan']);

    $json = Activity::all()->toJson();

    expect($json)->not->toContain('35202')
        ->not->toContain('61101')
        ->not->toContain('1234567-8')
        ->not->toContain('cnic')
        ->not->toContain('ntn')
        ->and(lastActivity()->event)->toBe('updated')
        ->and(lastActivity()->attribute_changes['attributes'])->toBe(['city' => 'Multan'])
        ->and(lastActivity()->attribute_changes['old'])->toBe(['city' => 'Lahore']);
});

it('logs settings changes with before and after values', function () {
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(ManageApprovalSettings::class)
        ->fillForm(['allow_founder_self_approval' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    $activity = lastActivity();

    expect($activity)
        ->log_name->toBe('settings')
        ->description->toBe('Updated Approvals settings')
        ->and($activity->attribute_changes['old']['allow_founder_self_approval'])->toBeTrue()
        ->and($activity->attribute_changes['attributes']['allow_founder_self_approval'])->toBeFalse()
        ->and($activity->attribute_changes['attributes'])->not->toHaveKey('second_level_threshold');
});

it('logs that a secret changed without logging the secret', function () {
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(ManageGatewaySettings::class)
        ->fillForm(['jazzcash_password' => 'super-secret-value'])
        ->call('save')
        ->assertHasNoFormErrors();

    $activity = lastActivity();

    expect($activity->attribute_changes['attributes']['jazzcash_password'])->toBe('[changed]')
        ->and(Activity::all()->toJson())->not->toContain('super-secret-value');
});

it('does not log a settings save that changed nothing', function () {
    $this->actingAs(staffUser(Role::SuperAdmin));
    $before = Activity::where('log_name', 'settings')->count();

    Livewire::test(ManageApprovalSettings::class)->call('save')->assertHasNoFormErrors();

    expect(Activity::where('log_name', 'settings')->count())->toBe($before);
});

it('shows the activity log to audit roles only', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/activity-log')->assertStatus($status);
})->with([
    'super admin' => [Role::SuperAdmin, 200],
    'manager' => [Role::Manager, 200],
    'trustee' => [Role::Trustee, 200],
    'auditor' => [Role::Auditor, 200],
    'finance' => [Role::Finance, 403],
    'campaign manager' => [Role::CampaignManager, 403],
    'field officer' => [Role::FieldOfficer, 403],
]);

it('opens a single activity with its changes', function () {
    $admin = staffUser(Role::SuperAdmin);
    $user = User::factory()->create(['name' => 'Before']);
    $user->update(['name' => 'After']);

    $this->actingAs($admin)
        ->get('/admin/activity-log/'.lastActivity()->id)
        ->assertOk()
        ->assertSee('name: Before → After');
});

it('keeps the audit trail read-only, even for super admin', function () {
    $admin = staffUser(Role::SuperAdmin);
    User::factory()->create();
    $activity = lastActivity();

    expect($admin->can('update', $activity))->toBeFalse()
        ->and($admin->can('delete', $activity))->toBeFalse()
        ->and(config('activitylog.clean_after_days'))->toBeGreaterThanOrEqual(7 * 365); // NFR-07
});
