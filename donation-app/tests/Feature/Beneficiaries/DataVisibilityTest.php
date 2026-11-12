<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Filament\Resources\Beneficiaries\BeneficiaryResource;
use App\Filament\Resources\Beneficiaries\Pages\ViewBeneficiary;
use App\Filament\Resources\VerificationVisits\VerificationVisitResource;
use App\Models\Application;
use App\Models\Beneficiary;
use App\Models\Donor;
use App\Models\User;
use App\Models\VerificationVisit;
use App\Services\VerificationService;
use App\Support\PermissionMatrix;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// W09-D5 — NFR-11, §3.1: beneficiary data is visible only to authorised roles, and field
// officers only ever reach their own cases — on every screen, search, policy and file.

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

/**
 * Two officers' caseloads plus an unassigned household, each with an application and a visit.
 *
 * @return array{a: User, b: User, mine: Beneficiary, theirs: Beneficiary, unassigned: Beneficiary}
 */
function caseloads(): array
{
    $a = staffUser(Role::FieldOfficer, ['name' => 'Officer A']);
    $b = staffUser(Role::FieldOfficer, ['name' => 'Officer B']);

    $mine = Beneficiary::factory()->create(['name' => 'Household Mine', 'assigned_officer_id' => $a->id]);
    $theirs = Beneficiary::factory()->create(['name' => 'Household Theirs', 'assigned_officer_id' => $b->id]);
    $unassigned = Beneficiary::factory()->create(['name' => 'Household Unassigned']);

    foreach ([[$mine, $a], [$theirs, $b], [$unassigned, null]] as [$household, $officer]) {
        $application = Application::factory()->create(['beneficiary_id' => $household->id, 'assigned_officer_id' => $officer?->id]);
        app(VerificationService::class)->recordVisit($application, [
            'visit_date' => today()->toDateString(),
            'findings' => 'Seen.',
            'recommendation' => 'approve',
            'officer_id' => $officer?->id,
        ], $officer);
    }

    return ['a' => $a, 'b' => $b, 'mine' => $mine, 'theirs' => $theirs, 'unassigned' => $unassigned];
}

/**
 * @return list<int>
 */
function visibleIds(string $resource, User $user): array
{
    test()->actingAs($user);

    /** @var class-string<BeneficiaryResource|ApplicationResource|VerificationVisitResource> $resource */
    return $resource::getEloquentQuery()->pluck((new ($resource::getModel()))->getQualifiedKeyName())->map(fn ($id): int => (int) $id)->sort()->values()->all();
}

it('shows each role exactly the beneficiary data the matrix and the data scope allow', function (Role $role) {
    ['a' => $a, 'mine' => $mine] = caseloads();
    $user = $role === Role::FieldOfficer ? $a : staffUser($role);
    $canSee = $role === Role::SuperAdmin || in_array('beneficiaries.view_any', PermissionMatrix::forRole($role->value), true);

    $all = [
        BeneficiaryResource::class => Beneficiary::withTrashed()->pluck('id'),
        ApplicationResource::class => Application::query()->pluck('id'),
        VerificationVisitResource::class => VerificationVisit::query()->pluck('id'),
    ];

    $own = [
        BeneficiaryResource::class => collect([$mine->id]),
        ApplicationResource::class => $mine->applications()->pluck('id'),
        VerificationVisitResource::class => VerificationVisit::query()->whereIn('application_id', $mine->applications()->pluck('id'))->pluck('id'),
    ];

    foreach ($all as $resource => $ids) {
        $expected = ! $canSee ? [] : ($role === Role::FieldOfficer ? $own[$resource] : $ids);
        $expected = collect($expected)->map(fn ($id): int => (int) $id)->sort()->values()->all();

        $listed = $canSee ? visibleIds($resource, $user) : [];

        expect($listed)->toBe($expected, "{$role->value} on {$resource}")
            ->and($user->can('viewAny', $resource::getModel()))->toBe($canSee);

        // The policy agrees with the list for every single record.
        foreach ($ids as $id) {
            $record = $resource::getModel()::query()->find($id);

            expect($user->can('view', $record))->toBe(in_array((int) $id, $expected, true), "{$role->value} view {$resource} #{$id}");
        }
    }
})->with(Role::cases());

it('blocks every beneficiary screen for a role without access', function () {
    ['theirs' => $household] = caseloads();
    $application = $household->applications()->firstOrFail();
    $this->actingAs(staffUser(Role::CampaignManager));

    foreach ([
        '/admin/beneficiaries', "/admin/beneficiaries/{$household->id}", "/admin/beneficiaries/{$household->id}/edit",
        '/admin/applications', "/admin/applications/{$application->id}", '/admin/verification-visits',
    ] as $url) {
        expect($this->get($url)->getStatusCode())->toBeIn([403, 404], $url);
    }
});

it('keeps donors (website accounts) out of the admin screens', function () {
    ['mine' => $household] = caseloads();
    $donor = Donor::factory()->create();

    $this->actingAs($donor, 'donor')->get("/admin/beneficiaries/{$household->id}")->assertRedirect();
    $this->actingAs($donor, 'donor')->get('/admin/applications')->assertRedirect();
});

describe('field officer scope', function () {
    it('drops a case from the previous officer when it is moved to another', function () {
        ['a' => $a, 'b' => $b, 'mine' => $household] = caseloads();
        $household->update(['registered_by' => $a->id]);

        expect($a->can('view', $household->fresh()))->toBeTrue();

        $household->update(['assigned_officer_id' => $b->id]);
        $household->applications()->update(['assigned_officer_id' => $b->id]);

        expect($a->can('view', $household->fresh()))->toBeFalse()
            ->and($b->can('view', $household->fresh()))->toBeTrue()
            ->and(visibleIds(BeneficiaryResource::class, $a))->not->toContain($household->id)
            ->and(visibleIds(ApplicationResource::class, $a))->toBe([]);
    });

    it('keeps a registrar’s case while nobody is assigned', function () {
        $officer = staffUser(Role::FieldOfficer);
        $household = Beneficiary::factory()->create(['registered_by' => $officer->id]);

        expect($officer->can('view', $household))->toBeTrue()
            ->and(visibleIds(BeneficiaryResource::class, $officer))->toBe([$household->id]);
    });

    it('finds only their own cases in global search', function () {
        ['a' => $a] = caseloads();
        $this->actingAs($a);

        $titles = BeneficiaryResource::getGlobalSearchResults('Household')->map(fn ($result): string => (string) $result->title)->values()->all();

        expect($titles)->toBe(['Household Mine']);
    });

    it('cannot open another officer’s records by URL', function () {
        ['a' => $a, 'theirs' => $theirs] = caseloads();
        $application = $theirs->applications()->firstOrFail();
        $this->actingAs($a);

        $this->get("/admin/beneficiaries/{$theirs->id}")->assertNotFound();
        $this->get("/admin/beneficiaries/{$theirs->id}/edit")->assertNotFound();
        $this->get("/admin/applications/{$application->id}")->assertNotFound();
        $this->get("/admin/applications/{$application->id}/edit")->assertNotFound();
    });
});

describe('view-only roles', function () {
    it('shows no buttons that change anything', function (Role $role) {
        ['mine' => $household] = caseloads();
        $application = $household->applications()->firstOrFail();
        $household->addMedia(UploadedFile::fake()->image('cnic.jpg'))->toMediaCollection(Beneficiary::DOCUMENTS);
        $this->actingAs(staffUser($role));

        Livewire::test(ViewBeneficiary::class, ['record' => $household->id])
            ->assertActionHidden('edit')
            ->assertActionHidden('newApplication')
            ->assertActionHidden('decideZakat')
            ->assertActionVisible('downloadDocument'); // viewing documents is part of "view"

        Livewire::test(ViewApplication::class, ['record' => $application->id])
            ->assertActionHidden('edit')
            ->assertActionHidden('recordVisit')
            ->assertActionHidden('startVerification')
            ->assertActionHidden('approve')
            ->assertActionHidden('reject')
            ->assertActionHidden('decideZakat');

        $this->get('/admin/beneficiaries/create')->assertForbidden();
        $this->get('/admin/applications/create')->assertForbidden();
    })->with([Role::Finance, Role::Trustee, Role::Auditor]);
});

describe('private data', function () {
    it('never serves documents or visit photos by a plain URL', function () {
        ['mine' => $household] = caseloads();
        $household->addMedia(UploadedFile::fake()->image('cnic.jpg'))->toMediaCollection(Beneficiary::DOCUMENTS);
        $visit = VerificationVisit::query()->firstOrFail();
        Storage::disk('local')->put('visit-uploads/room.jpg', UploadedFile::fake()->image('room.jpg')->getContent());
        app(VerificationService::class)->recordVisit($visit->application, [
            'visit_date' => today()->toDateString(), 'findings' => 'Again', 'recommendation' => 'approve', 'photos' => ['visit-uploads/room.jpg'],
        ]);

        $files = [
            $household->getFirstMedia(Beneficiary::DOCUMENTS),
            VerificationVisit::query()->latest('id')->firstOrFail()->getFirstMedia(VerificationVisit::PHOTOS),
        ];

        $this->actingAs(staffUser(Role::SuperAdmin));

        foreach ($files as $media) {
            expect($media?->disk)->toBe('local');

            $path = $media?->getPathRelativeToRoot() ?? '';

            expect($this->get('/storage/'.$path)->getStatusCode())->not->toBe(200, "/storage/{$path}")
                ->and(Storage::disk('public')->exists($path))->toBeFalse();
        }
    });

    it('keeps the CNIC out of JSON, screens and the audit log', function () {
        $household = Beneficiary::factory()->create(['cnic' => '35202-8642097-3']);
        Application::factory()->create(['beneficiary_id' => $household->id]);
        $this->actingAs(staffUser(Role::Auditor));

        expect($household->toJson())->not->toContain('8642097')
            ->and(Activity::all()->toJson())->not->toContain('8642097');

        foreach (['/admin/beneficiaries', "/admin/beneficiaries/{$household->id}", '/admin/applications', '/admin/activity-log'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('8642097');
        }
    });

    it('has no API endpoint for beneficiaries, applications or visits', function () {
        $exposed = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/'))
            ->filter(fn ($route): bool => preg_match('/beneficiar|application|visit/i', $route->uri()) === 1)
            ->map(fn ($route): string => $route->uri())
            ->values()
            ->all();

        expect($exposed)->toBe([]);
    });
});
