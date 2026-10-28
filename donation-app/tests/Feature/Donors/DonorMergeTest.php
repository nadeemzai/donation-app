<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Resources\Donors\Pages\EditDonor;
use App\Filament\Resources\Donors\Pages\ListDonors;
use App\Models\Donor;
use App\Services\DonorMergeService;
use App\Support\Cnic;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// FR-DNR-04 — finding and merging duplicate donors

function merger(): DonorMergeService
{
    return app(DonorMergeService::class);
}

it('suggests donors with the same name, ignoring case and spaces, same city first', function () {
    $donor = Donor::factory()->create(['name' => 'Ayesha Khan', 'city' => 'Lahore']);
    $otherCity = Donor::factory()->create(['name' => 'ayesha  khan ', 'city' => 'Karachi']);
    $sameCity = Donor::factory()->create(['name' => 'AYESHA KHAN', 'city' => 'Lahore']);
    Donor::factory()->create(['name' => 'Ayesha Malik']);

    expect(merger()->possibleDuplicatesOf($donor)->pluck('id')->all())->toBe([$sameCity->id, $otherCity->id]);
});

it('keeps the survivor\'s details and fills only its empty fields', function () {
    $survivor = Donor::factory()->guest()->create(['name' => 'Ayesha Khan', 'phone' => '03001234567', 'email' => null, 'city' => 'Lahore']);
    $duplicate = Donor::factory()->create(['name' => 'Ayesha Khan', 'phone' => '03339876543', 'email' => 'ayesha@example.org', 'city' => 'Karachi', 'cnic' => '42101-9876543-2']);

    $merged = merger()->merge($duplicate, $survivor);

    expect($merged)
        ->phone->toBe('03001234567')
        ->city->toBe('Lahore')
        ->email->toBe('ayesha@example.org')
        ->cnic->toBe('42101-9876543-2')
        ->cnic_hash->toBe(Cnic::hash('4210198765432'))
        ->and($merged->isGuest())->toBeFalse(); // took the duplicate's login
});

it('archives the duplicate and points it at the survivor', function () {
    $survivor = Donor::factory()->create();
    $duplicate = Donor::factory()->create();
    $duplicate->createToken('mobile');

    merger()->merge($duplicate, $survivor);

    $archived = Donor::withTrashed()->find($duplicate->id);

    expect($archived->trashed())->toBeTrue()
        ->and($archived->merged_into_id)->toBe($survivor->id)
        ->and($archived->merged_at)->not->toBeNull()
        ->and($archived->email)->toBeNull()
        ->and($archived->phone)->toBeNull()
        ->and($archived->tokens()->count())->toBe(0)
        ->and(Donor::count())->toBe(1);
});

it('records the merge in the audit trail', function () {
    $survivor = Donor::factory()->create();
    $duplicate = Donor::factory()->create();

    merger()->merge($duplicate, $survivor);

    $activity = Activity::where('event', 'merged')->sole();

    expect($activity->subject_id)->toBe($survivor->id)
        ->and($activity->properties['duplicate_code'])->toBe($duplicate->donor_code);
});

it('refuses to merge a donor into itself or merge twice', function () {
    $a = Donor::factory()->create();
    $b = Donor::factory()->create();

    expect(fn () => merger()->merge($a, $a))->toThrow(DomainException::class, 'into itself');

    merger()->merge($b, $a);

    expect(fn () => merger()->merge($b, $a))->toThrow(DomainException::class, 'already been merged');
});

it('refuses to merge an archived donor', function () {
    $survivor = Donor::factory()->create();
    $archived = Donor::factory()->create();
    $archived->delete();

    expect(fn () => merger()->merge($archived, $survivor))->toThrow(DomainException::class, 'Archived donors cannot be merged');
});

it('merges from the donor edit page', function () {
    $survivor = Donor::factory()->create(['name' => 'Bilal Ahmed']);
    $duplicate = Donor::factory()->create(['name' => 'Bilal Ahmed']);
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(EditDonor::class, ['record' => $survivor->getRouteKey()])
        ->callAction('mergeDuplicate', data: ['duplicate_id' => $duplicate->id])
        ->assertNotified('Donors merged');

    expect(Donor::withTrashed()->find($duplicate->id)->merged_into_id)->toBe($survivor->id);
});

it('lets only donor editors merge', function () {
    $donor = Donor::factory()->create();

    expect(staffUser(Role::Finance)->can('merge', $donor))->toBeTrue()
        ->and(staffUser(Role::CampaignManager)->can('merge', $donor))->toBeFalse()
        ->and(staffUser(Role::Auditor)->can('merge', $donor))->toBeFalse();
});

it('filters the donor list to possible duplicates', function () {
    $this->actingAs(staffUser(Role::Finance));
    $twinA = Donor::factory()->create(['name' => 'Sara Iqbal']);
    $twinB = Donor::factory()->create(['name' => 'sara iqbal']);
    $unique = Donor::factory()->create(['name' => 'Usman Tariq']);

    Livewire::test(ListDonors::class)
        ->filterTable('possible_duplicates')
        ->assertCanSeeTableRecords([$twinA, $twinB])
        ->assertCanNotSeeTableRecords([$unique]);
});

it('collapses extra spaces in donor names when saving', function () {
    expect(Donor::factory()->create(['name' => '  Hina   Saleem '])->name)->toBe('Hina Saleem');
});
