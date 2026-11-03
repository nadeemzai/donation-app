<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\Vulnerability;
use App\Filament\Resources\Beneficiaries\Pages\CreateBeneficiary;
use App\Filament\Resources\Beneficiaries\Pages\EditBeneficiary;
use App\Filament\Resources\Beneficiaries\Pages\ListBeneficiaries;
use App\Filament\Resources\Beneficiaries\Pages\ViewBeneficiary;
use App\Models\Beneficiary;
use App\Models\User;
use App\Support\Cnic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// W09-D2 — FR-BEN-01, §3.1 (area: beneficiaries): the Beneficiaries screen, private documents,
// field officers limited to their own cases.

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

/** A tiny real PDF — the media library checks the file's content, not its name. */
function fakePdf(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
}

/**
 * @param  array<string, mixed>  $attributes
 */
function household(?User $officer = null, array $attributes = []): Beneficiary
{
    return Beneficiary::factory()->create([...$attributes, 'assigned_officer_id' => $officer?->id ?? ($attributes['assigned_officer_id'] ?? null)]);
}

it('lets each role open the beneficiaries list as the matrix allows', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/beneficiaries')->assertStatus($status);
})->with([
    'super admin' => [Role::SuperAdmin, 200],
    'manager' => [Role::Manager, 200],
    'finance (view)' => [Role::Finance, 200],
    'field officer' => [Role::FieldOfficer, 200],
    'trustee (view)' => [Role::Trustee, 200],
    'auditor (view)' => [Role::Auditor, 200],
    'campaign manager' => [Role::CampaignManager, 403],
]);

it('lets only create-level roles register a beneficiary', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/beneficiaries/create')->assertStatus($status);
})->with([
    'manager' => [Role::Manager, 200],
    'field officer' => [Role::FieldOfficer, 200],
    'finance' => [Role::Finance, 403],
    'trustee' => [Role::Trustee, 403],
    'auditor' => [Role::Auditor, 403],
]);

it('registers a beneficiary, normalising CNIC and phone and recording the registrar', function () {
    $manager = staffUser(Role::Manager);
    $officer = staffUser(Role::FieldOfficer);
    $this->actingAs($manager);

    Livewire::test(CreateBeneficiary::class)
        ->fillForm([
            'name' => 'Shamim Bibi',
            'guardian_name' => 'Late Muhammad Aslam',
            'cnic' => '3520212345671',
            'phone' => '+92 300 1234567',
            'city' => 'Lahore',
            'household_size' => 5,
            'dependents' => 4,
            'monthly_income' => 8000,
            'vulnerabilities' => [Vulnerability::Widow->value, Vulnerability::NoEarner->value],
            'assigned_officer_id' => $officer->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $beneficiary = Beneficiary::query()->sole();

    expect($beneficiary)
        ->code->toStartWith('BN-')
        ->cnic->toBe('35202-1234567-1')
        ->cnic_hash->toBe(Cnic::hash('3520212345671'))
        ->phone->toBe('03001234567')
        ->registered_by->toBe($manager->id)
        ->assigned_officer_id->toBe($officer->id)
        ->and($beneficiary->hasVulnerability(Vulnerability::NoEarner))->toBeTrue();
});

it('assigns a field officer’s new case to that officer, whatever the form sends', function () {
    $officer = staffUser(Role::FieldOfficer);
    $other = staffUser(Role::FieldOfficer);
    $this->actingAs($officer);

    Livewire::test(CreateBeneficiary::class)
        ->assertFormFieldHidden('assigned_officer_id')
        ->fillForm(['name' => 'Rukhsana', 'household_size' => 3, 'dependents' => 2, 'monthly_income' => 0])
        ->set('data.assigned_officer_id', $other->id)
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Beneficiary::query()->sole())
        ->assigned_officer_id->toBe($officer->id)
        ->registered_by->toBe($officer->id);
});

it('rejects a CNIC that is already registered, in any format, naming only the code', function () {
    $existing = household(attributes: ['cnic' => '35202-1234567-1']);
    $this->actingAs(staffUser(Role::Manager));

    Livewire::test(CreateBeneficiary::class)
        ->fillForm(['name' => 'Duplicate', 'cnic' => '3520212345671', 'household_size' => 1, 'dependents' => 0, 'monthly_income' => 0])
        ->call('create')
        ->assertHasFormErrors(['cnic']);

    expect(Beneficiary::query()->count())->toBe(1)
        ->and($existing->code)->toStartWith('BN-');
});

it('checks household numbers on the form', function () {
    $this->actingAs(staffUser(Role::Manager));

    Livewire::test(CreateBeneficiary::class)
        ->fillForm(['name' => 'Bad numbers', 'household_size' => 2, 'dependents' => 3, 'monthly_income' => -10, 'phone' => '12'])
        ->call('create')
        ->assertHasFormErrors(['dependents', 'monthly_income', 'phone']);
});

it('never sends the CNIC to the browser and keeps it when left empty', function () {
    // Distinctive digits: "1234567" also turns up in unrelated page markup.
    $beneficiary = household(attributes: ['cnic' => '35202-8642097-3']);
    $this->actingAs(staffUser(Role::Manager));

    $this->get("/admin/beneficiaries/{$beneficiary->id}/edit")
        ->assertOk()
        ->assertDontSee('8642097')
        ->assertDontSee('3520286420973')
        ->assertSee('35202-•••••••-3');

    Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getRouteKey()])
        ->assertFormSet(['cnic' => null])
        ->fillForm(['city' => 'Multan'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($beneficiary->fresh())
        ->city->toBe('Multan')
        ->cnic->toBe('35202-8642097-3');
});

it('shows the CNIC masked on the view page', function () {
    $beneficiary = household(attributes: ['cnic' => '35202-8642097-3']);

    $this->actingAs(staffUser(Role::Auditor))
        ->get("/admin/beneficiaries/{$beneficiary->id}")
        ->assertOk()
        ->assertSee('35202-•••••••-3')
        ->assertDontSee('8642097');
});

it('finds a beneficiary by full CNIC in any format', function () {
    $match = household(attributes: ['cnic' => '35202-1234567-1']);
    $other = household(attributes: ['cnic' => '35202-7654321-1']);
    $this->actingAs(staffUser(Role::Manager));

    Livewire::test(ListBeneficiaries::class)
        ->searchTable('3520212345671')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});

describe('field officer scope', function () {
    it('lists only their own cases', function () {
        $officer = staffUser(Role::FieldOfficer);
        $colleague = staffUser(Role::FieldOfficer);

        $assigned = household($officer);
        $registered = household(attributes: ['registered_by' => $officer->id]);
        $notMine = household($colleague);
        $unassigned = household();

        $this->actingAs($officer);

        Livewire::test(ListBeneficiaries::class)
            ->assertCanSeeTableRecords([$assigned, $registered])
            ->assertCanNotSeeTableRecords([$notMine, $unassigned]);
    });

    it('cannot open or edit another officer’s case by URL', function () {
        $officer = staffUser(Role::FieldOfficer);
        $notMine = household(staffUser(Role::FieldOfficer));

        $this->actingAs($officer);

        $this->get("/admin/beneficiaries/{$notMine->id}")->assertNotFound();
        $this->get("/admin/beneficiaries/{$notMine->id}/edit")->assertNotFound();
    });

    it('edits their own case but cannot reassign it', function () {
        $officer = staffUser(Role::FieldOfficer);
        $other = staffUser(Role::FieldOfficer);
        $mine = household($officer);

        $this->actingAs($officer);

        Livewire::test(EditBeneficiary::class, ['record' => $mine->getRouteKey()])
            ->assertFormFieldHidden('assigned_officer_id')
            ->fillForm(['area' => 'Gulshan-e-Ravi'])
            ->set('data.assigned_officer_id', $other->id)
            ->call('save')
            ->assertHasNoFormErrors();

        expect($mine->fresh())
            ->area->toBe('Gulshan-e-Ravi')
            ->assigned_officer_id->toBe($officer->id);
    });

    it('does not see another officer’s case through the policy either', function () {
        $officer = staffUser(Role::FieldOfficer);
        $mine = household($officer);
        $notMine = household(staffUser(Role::FieldOfficer));

        expect($officer->can('view', $mine))->toBeTrue()
            ->and($officer->can('update', $mine))->toBeTrue()
            ->and($officer->can('view', $notMine))->toBeFalse()
            ->and($officer->can('update', $notMine))->toBeFalse()
            ->and($officer->can('delete', $mine))->toBeFalse();
    });

    it('leaves other roles seeing every case', function (Role $role) {
        $user = staffUser($role);
        $case = household(staffUser(Role::FieldOfficer));

        expect(Beneficiary::seesOnlyOwnCases($user))->toBeFalse()
            ->and($user->can('view', $case))->toBeTrue();
    })->with([Role::Manager, Role::Finance, Role::Trustee, Role::Auditor]);
});

describe('archiving', function () {
    it('lets only the super admin archive, and keeps the record', function () {
        $beneficiary = household();

        expect(staffUser(Role::Manager)->can('delete', $beneficiary))->toBeFalse();

        $this->actingAs(staffUser(Role::SuperAdmin));

        Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getRouteKey()])
            ->callAction('delete');

        expect(Beneficiary::withTrashed()->find($beneficiary->id)?->trashed())->toBeTrue();
    });

    it('does not let anyone edit an archived beneficiary', function () {
        $beneficiary = household();
        $beneficiary->delete();

        $archived = Beneficiary::withTrashed()->findOrFail($beneficiary->id);

        expect(staffUser(Role::SuperAdmin)->can('update', $archived))->toBeFalse()
            ->and(staffUser(Role::Manager)->can('update', $archived))->toBeFalse()
            ->and(staffUser(Role::SuperAdmin)->can('restore', $archived))->toBeTrue();
    });
});

describe('documents', function () {
    it('stores documents on the private disk', function () {
        $beneficiary = household();
        $beneficiary->addMedia(fakePdf('cnic-copy.pdf'))->toMediaCollection(Beneficiary::DOCUMENTS);

        $media = $beneficiary->getFirstMedia(Beneficiary::DOCUMENTS);

        expect($media?->disk)->toBe('local')
            ->and(Storage::disk('local')->exists($media?->getPathRelativeToRoot() ?? ''))->toBeTrue()
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('downloads a document for staff who can see the case, and logs it', function () {
        $officer = staffUser(Role::FieldOfficer);
        $beneficiary = household($officer);
        $beneficiary->addMedia(fakePdf('bill.pdf'))->toMediaCollection(Beneficiary::DOCUMENTS);
        $media = $beneficiary->getFirstMedia(Beneficiary::DOCUMENTS);

        $this->actingAs($officer);

        Livewire::test(ViewBeneficiary::class, ['record' => $beneficiary->getRouteKey()])
            ->assertActionVisible('downloadDocument')
            ->callAction('downloadDocument', data: ['media_id' => $media?->id])
            ->assertFileDownloaded('bill.pdf');

        $log = Activity::query()->where('event', 'document_downloaded')->sole();

        expect($log->causer_id)->toBe($officer->id)
            ->and($log->subject_id)->toBe($beneficiary->id);
    });

    it('hides the download when there are no documents', function () {
        $beneficiary = household();
        $this->actingAs(staffUser(Role::Manager));

        Livewire::test(ViewBeneficiary::class, ['record' => $beneficiary->getRouteKey()])
            ->assertActionHidden('downloadDocument');
    });

    it('keeps the CNIC out of the audit log', function () {
        household(attributes: ['cnic' => '35202-8642097-3']);

        expect(Activity::all()->toJson())->not->toContain('8642097');
    });
});
