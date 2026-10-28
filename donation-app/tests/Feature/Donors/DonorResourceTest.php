<?php

declare(strict_types=1);

use App\Enums\DonorType;
use App\Enums\Role;
use App\Filament\Resources\Donors\Pages\CreateDonor;
use App\Filament\Resources\Donors\Pages\EditDonor;
use App\Filament\Resources\Donors\Pages\ListDonors;
use App\Models\Donor;
use App\Support\Cnic;
use Livewire\Livewire;

// FR-DNR-01, FR-DNR-02, NFR-04, §3.1 (area: donors) — donors screen

it('lets each role open the donors list as the matrix allows', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/donors')->assertStatus($status);
})->with([
    'super admin' => [Role::SuperAdmin, 200],
    'manager' => [Role::Manager, 200],
    'finance' => [Role::Finance, 200],
    'campaign manager (view)' => [Role::CampaignManager, 200],
    'trustee (view)' => [Role::Trustee, 200],
    'auditor (view)' => [Role::Auditor, 200],
    'field officer' => [Role::FieldOfficer, 403],
]);

it('lets only create-level roles add a donor', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/donors/create')->assertStatus($status);
})->with([
    'finance' => [Role::Finance, 200],
    'manager' => [Role::Manager, 200],
    'campaign manager' => [Role::CampaignManager, 403],
    'auditor' => [Role::Auditor, 403],
]);

it('creates a donor, normalising phone, email and CNIC', function () {
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(CreateDonor::class)
        ->fillForm([
            'type' => DonorType::Individual->value,
            'name' => 'Ayesha Khan',
            'phone' => '+92 300 1234567',
            'email' => 'Ayesha@Example.ORG',
            'city' => 'Lahore',
            'cnic' => '3520212345671',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $donor = Donor::where('phone', '03001234567')->sole();

    expect($donor)
        ->email->toBe('ayesha@example.org')
        ->cnic->toBe('35202-1234567-1')
        ->cnic_hash->toBe(Cnic::hash('3520212345671'))
        ->donor_code->toStartWith('DR-')
        ->and($donor->isGuest())->toBeTrue();
});

it('needs a phone number or an email', function () {
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(CreateDonor::class)
        ->fillForm(['type' => DonorType::Individual->value, 'name' => 'No Contact', 'phone' => null, 'email' => null])
        ->call('create')
        ->assertHasFormErrors(['phone', 'email']);
});

it('rejects an invalid phone number and an invalid CNIC', function () {
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(CreateDonor::class)
        ->fillForm(['type' => DonorType::Individual->value, 'name' => 'Bad Data', 'phone' => '12345', 'cnic' => '1234'])
        ->call('create')
        ->assertHasFormErrors(['phone', 'cnic']);
});

it('rejects a CNIC or phone that another donor already has, in any format', function () {
    Donor::factory()->create(['cnic' => '35202-1234567-1', 'phone' => '03001234567']);
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(CreateDonor::class)
        ->fillForm(['type' => DonorType::Individual->value, 'name' => 'Duplicate', 'phone' => '0300-1234567', 'cnic' => '3520212345671'])
        ->call('create')
        ->assertHasFormErrors(['phone', 'cnic']);
});

it('never sends the CNIC or NTN to the browser when editing', function () {
    // Deliberately different from the field's placeholder example (35202-1234567-1).
    $donor = Donor::factory()->create(['cnic' => '42101-9876543-2', 'ntn' => '7654321-8']);
    $this->actingAs(staffUser(Role::Finance));

    $page = Livewire::test(EditDonor::class, ['record' => $donor->getRouteKey()]);

    expect($page->get('data.cnic'))->toBeNull()
        ->and($page->get('data.ntn'))->toBeNull()
        ->and($page->html())
        ->not->toContain('42101-9876543-2')
        ->not->toContain('4210198765432')
        ->not->toContain('7654321-8')
        ->toContain('42101-•••••••-2');
});

it('keeps the stored CNIC when the field is left empty, and replaces it when filled', function () {
    $donor = Donor::factory()->create(['cnic' => '35202-1234567-1']);
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(EditDonor::class, ['record' => $donor->getRouteKey()])
        ->fillForm(['city' => 'Karachi'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($donor->fresh())->cnic->toBe('35202-1234567-1')->city->toBe('Karachi');

    Livewire::test(EditDonor::class, ['record' => $donor->getRouteKey()])
        ->fillForm(['cnic' => '61101-7654321-9'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($donor->fresh())
        ->cnic->toBe('61101-7654321-9')
        ->cnic_hash->toBe(Cnic::hash('6110176543219'));
});

it('finds a donor by full CNIC in any format, or by donor code', function () {
    $this->actingAs(staffUser(Role::Finance));
    $target = Donor::factory()->create(['cnic' => '35202-1234567-1']);
    $other = Donor::factory()->create(['cnic' => '61101-7654321-9']);

    Livewire::test(ListDonors::class)
        ->searchTable('3520212345671')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$other]);

    Livewire::test(ListDonors::class)
        ->searchTable($other->donor_code)
        ->assertCanSeeTableRecords([$other])
        ->assertCanNotSeeTableRecords([$target]);
});

it('masks the CNIC and NTN for display', function () {
    $donor = Donor::factory()->make(['cnic' => '35202-1234567-1', 'ntn' => '7654321-8']);

    expect($donor->maskedCnic())->toBe('35202-•••••••-1')
        ->and($donor->maskedNtn())->toBe('•••••••-8');
});

it('archives donors instead of deleting them', function () {
    $donor = Donor::factory()->create();
    $admin = staffUser(Role::SuperAdmin);

    expect($admin->can('delete', $donor))->toBeTrue()
        ->and($admin->can('forceDelete', $donor))->toBeFalse()
        ->and(staffUser(Role::Finance)->can('delete', $donor))->toBeFalse();
});
