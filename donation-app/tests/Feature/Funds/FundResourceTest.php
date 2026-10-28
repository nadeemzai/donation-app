<?php

declare(strict_types=1);

use App\Enums\FundType;
use App\Enums\Role;
use App\Filament\Resources\Funds\Pages\CreateFund;
use App\Filament\Resources\Funds\Pages\EditFund;
use App\Models\Campaign;
use App\Models\Fund;
use Livewire\Livewire;

// FR-CMP-01, §3.1 (area: funds) — funds screen

it('lets each role open the funds list as the matrix allows', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/funds')->assertStatus($status);
})->with([
    'super admin' => [Role::SuperAdmin, 200],
    'manager' => [Role::Manager, 200],
    'finance' => [Role::Finance, 200],
    'campaign manager' => [Role::CampaignManager, 200],
    'trustee (view)' => [Role::Trustee, 200],
    'auditor (view)' => [Role::Auditor, 200],
    'field officer' => [Role::FieldOfficer, 403],
]);

it('lets only create-level roles add a fund', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/funds/create')->assertStatus($status);
})->with([
    'manager' => [Role::Manager, 200],
    'campaign manager' => [Role::CampaignManager, 200],
    'finance' => [Role::Finance, 200],
    'trustee' => [Role::Trustee, 403],
    'auditor' => [Role::Auditor, 403],
]);

it('lets Finance create a fund without being able to create campaigns', function () {
    $finance = staffUser(Role::Finance);

    expect($finance->can('funds.create'))->toBeTrue()
        ->and($finance->can('funds.update'))->toBeTrue()
        ->and($finance->can('campaigns.create'))->toBeFalse();
});

it('creates a fund', function () {
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(CreateFund::class)
        ->fillForm(['code' => 'education', 'name' => 'Education Support', 'type' => FundType::General->value, 'sort_order' => 70, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Fund::where('code', 'EDUCATION')->sole())
        ->name->toBe('Education Support')
        ->is_zakat->toBeFalse()
        ->balance->toBe('0.00');
});

it('requires a restriction for a restricted fund', function () {
    $this->actingAs(staffUser(Role::Manager));

    Livewire::test(CreateFund::class)
        ->fillForm(['code' => 'MEDICAL', 'name' => 'Medical', 'type' => FundType::Restricted->value, 'sort_order' => 1])
        ->call('create')
        ->assertHasFormErrors(['restriction' => 'required']);
});

it('rejects a duplicate fund code, whatever the letter case', function () {
    Fund::factory()->create(['code' => 'ZAKAT']);
    $this->actingAs(staffUser(Role::Manager));

    Livewire::test(CreateFund::class)
        ->fillForm(['code' => 'zakat', 'name' => 'Zakat again', 'type' => FundType::Zakat->value, 'sort_order' => 1])
        ->call('create')
        ->assertHasFormErrors(['code']);

    expect(Fund::count())->toBe(1);
});

it('does not let the code be changed after creation', function () {
    $fund = Fund::factory()->create(['code' => 'ORIGINAL']);
    $this->actingAs(staffUser(Role::Manager));

    Livewire::test(EditFund::class, ['record' => $fund->getRouteKey()])
        ->fillForm(['code' => 'CHANGED', 'name' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($fund->fresh())->code->toBe('ORIGINAL')->name->toBe('Renamed');
});

it('only allows deleting a fund with no campaigns and no money', function () {
    $admin = staffUser(Role::SuperAdmin);
    $empty = Fund::factory()->create();
    $used = Fund::factory()->create();
    Campaign::factory()->for($used)->create();

    expect($admin->can('delete', $empty))->toBeTrue()
        ->and($admin->can('delete', $used))->toBeFalse();
});

it('never lets anyone set the balance from the form', function () {
    $fund = Fund::factory()->create();
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(EditFund::class, ['record' => $fund->getRouteKey()])
        ->fillForm(['balance' => '500000'])
        ->call('save');

    expect($fund->fresh()->balance)->toBe('0.00');
});
