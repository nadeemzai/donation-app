<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;

// FR-ACC-02 — staff users screen

it('lets each role open (or not open) the staff users screen', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/users')->assertStatus($status);
})->with([
    'super admin' => [Role::SuperAdmin, 200],
    'manager (view)' => [Role::Manager, 200],
    'auditor (view)' => [Role::Auditor, 200],
    'finance' => [Role::Finance, 403],
    'campaign manager' => [Role::CampaignManager, 403],
    'field officer' => [Role::FieldOfficer, 403],
    'trustee' => [Role::Trustee, 403],
]);

it('only lets super admin open the create page', function () {
    $this->actingAs(staffUser(Role::Manager))->get('/admin/users/create')->assertForbidden();
    $this->actingAs(staffUser(Role::SuperAdmin))->get('/admin/users/create')->assertOk();
});

it('creates a staff user with a role', function () {
    $this->actingAs(staffUser(Role::SuperAdmin));
    $financeRole = RoleModel::findByName(Role::Finance->value, 'web');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Ayesha Khan',
            'email' => 'ayesha@example.org',
            'phone' => '03001234567',
            'roles' => [$financeRole->id],
            'password' => 'a-strong-password',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'ayesha@example.org')->sole();

    expect($user->hasRole(Role::Finance->value))->toBeTrue()
        ->and(Hash::check('a-strong-password', $user->password))->toBeTrue();
});

it('does not let you deactivate your own account', function () {
    $admin = staffUser(Role::SuperAdmin);
    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save');

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('lets super admin deactivate another user, who then cannot log in to the panel', function () {
    $this->actingAs(staffUser(Role::SuperAdmin));
    $other = staffUser(Role::Finance);

    Livewire::test(EditUser::class, ['record' => $other->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($other->fresh()->is_active)->toBeFalse();

    $this->actingAs($other->fresh())->get('/admin')->assertForbidden();
});
