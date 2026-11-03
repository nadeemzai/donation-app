<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Enums\BeneficiaryStatus;
use App\Enums\Vulnerability;
use App\Models\Application;
use App\Models\Beneficiary;
use App\Models\VerificationVisit;
use App\Support\Cnic;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// W09-D1 — FR-BEN-01..03: beneficiaries, applications and verification visits

describe('beneficiaries', function () {
    it('gets a BN code from the number sequence', function () {
        $first = Beneficiary::factory()->create();
        $second = Beneficiary::factory()->create();

        $year = now()->year;

        expect($first->code)->toBe("BN-{$year}-000001")
            ->and($second->code)->toBe("BN-{$year}-000002")
            ->and($first->status)->toBe(BeneficiaryStatus::Registered)
            ->and($first->is_zakat_eligible)->toBeFalse();
    });

    it('never changes its code', function () {
        $beneficiary = Beneficiary::factory()->create();

        $beneficiary->forceFill(['code' => 'BN-1999-000001'])->save();
    })->throws(DomainException::class, 'can never change');

    it('stores the CNIC encrypted, formatted, with a lookup hash', function () {
        $beneficiary = Beneficiary::factory()->create(['cnic' => '3520212345671']);

        $raw = DB::table('beneficiaries')->where('id', $beneficiary->id)->value('cnic');

        expect($raw)->not->toContain('3520212345671')
            ->and($raw)->not->toContain('35202-1234567-1')
            ->and($beneficiary->fresh()->cnic)->toBe('35202-1234567-1')
            ->and($beneficiary->cnic_hash)->toBe(Cnic::hash('3520212345671'));
    });

    it('rejects the same CNIC written differently', function () {
        Beneficiary::factory()->create(['cnic' => '35202-1234567-1']);
        Beneficiary::factory()->create(['cnic' => '3520212345671']);
    })->throws(QueryException::class);

    it('rejects an invalid CNIC', function () {
        Beneficiary::factory()->create(['cnic' => '12345']);
    })->throws(DomainException::class, '13-digit CNIC');

    it('allows several beneficiaries without a CNIC', function () {
        Beneficiary::factory()->withoutCnic()->count(2)->create();

        expect(Beneficiary::query()->whereNull('cnic_hash')->count())->toBe(2);
    });

    it('masks the CNIC and hides it from JSON', function () {
        $beneficiary = Beneficiary::factory()->create(['cnic' => '35202-1234567-1']);

        expect($beneficiary->maskedCnic())->toBe('35202-•••••••-1')
            ->and($beneficiary->toArray())->not->toHaveKeys(['cnic', 'cnic_hash']);
    });

    it('normalises the name and phone', function () {
        $beneficiary = Beneficiary::factory()->create(['name' => '  Shamim   Bibi ', 'phone' => '+92 300 1234567']);

        expect($beneficiary->name)->toBe('Shamim Bibi')
            ->and($beneficiary->phone)->toBe('03001234567');
    });

    it('rejects an invalid phone number', function () {
        Beneficiary::factory()->create(['phone' => '12']);
    })->throws(DomainException::class);

    it('checks household numbers', function (array $attributes, string $message) {
        expect(fn () => Beneficiary::factory()->create($attributes))->toThrow(DomainException::class, $message);
    })->with([
        'empty household' => [['household_size' => 0, 'dependents' => 0], 'at least one person'],
        'too many dependents' => [['household_size' => 3, 'dependents' => 4], 'more than the household size'],
        'negative income' => [['monthly_income' => '-1'], 'cannot be negative'],
    ]);

    it('works out income per person and vulnerabilities', function () {
        $beneficiary = Beneficiary::factory()->create([
            'household_size' => 4,
            'dependents' => 3,
            'monthly_income' => '10000',
            'vulnerabilities' => [Vulnerability::Widow->value, Vulnerability::NoEarner->value],
        ]);

        expect($beneficiary->incomePerPerson())->toBe('2500.00')
            ->and($beneficiary->hasVulnerability(Vulnerability::NoEarner))->toBeTrue()
            ->and($beneficiary->hasVulnerability(Vulnerability::Orphan))->toBeFalse();
    });

    it('does not let status or the Zakat decision be mass-assigned', function () {
        $beneficiary = Beneficiary::factory()->create();
        $beneficiary->fill(['status' => 'verified', 'is_zakat_eligible' => true])->save();

        expect($beneficiary->fresh())
            ->status->toBe(BeneficiaryStatus::Registered)
            ->is_zakat_eligible->toBeFalse();
    });

    it('is archived, not deleted, and cannot be force-deleted with applications', function () {
        $application = Application::factory()->create();
        $beneficiary = $application->beneficiary;

        $beneficiary->delete();

        expect(Beneficiary::withTrashed()->find($beneficiary->id)?->trashed())->toBeTrue()
            ->and($application->fresh()->beneficiary->id)->toBe($beneficiary->id);

        expect(fn () => $beneficiary->forceDelete())->toThrow(QueryException::class);
    });
});

describe('applications', function () {
    it('gets an AP number and a submitted time', function () {
        $application = Application::factory()->create();

        expect($application->application_no)->toBe('AP-'.now()->year.'-000001')
            ->and($application->status)->toBe(ApplicationStatus::Submitted)
            ->and($application->submitted_at)->not->toBeNull()
            ->and($application->beneficiary->applications)->toHaveCount(1);
    });

    it('does not let status or the decision be mass-assigned', function () {
        $application = Application::factory()->create();
        $application->fill(['status' => 'approved', 'approved_amount' => '5000', 'decided_by' => 1])->save();

        expect($application->fresh())
            ->status->toBe(ApplicationStatus::Submitted)
            ->approved_amount->toBeNull()
            ->decided_by->toBeNull();
    });

    it('needs a description and a positive amount', function (array $attributes, string $message) {
        expect(fn () => Application::factory()->create($attributes))->toThrow(DomainException::class, $message);
    })->with([
        'blank description' => [['description' => '   '], 'Describe what help'],
        'zero amount' => [['requested_amount' => '0'], 'more than zero'],
    ]);

    it('allows an application without an amount (e.g. ration in kind)', function () {
        expect(Application::factory()->create(['requested_amount' => null])->requested_amount)->toBeNull();
    });

    it('follows the allowed status steps', function () {
        $application = Application::factory()->create();

        $application->forceFill(['status' => ApplicationStatus::Verifying])->save();
        $application->forceFill(['status' => ApplicationStatus::Rejected])->save();
        $application->forceFill(['status' => ApplicationStatus::Verifying])->save();
        $application->forceFill(['status' => ApplicationStatus::Approved, 'approved_amount' => '10000'])->save();

        expect($application->fresh()->status)->toBe(ApplicationStatus::Approved);
    });

    it('cannot skip verification', function () {
        Application::factory()->create()->forceFill(['status' => ApplicationStatus::Approved])->save();
    })->throws(DomainException::class, 'cannot go from Submitted to Approved');

    it('is frozen once approved', function () {
        $application = Application::factory()->create();
        $application->forceFill(['status' => ApplicationStatus::Verifying])->save();
        $application->forceFill(['status' => ApplicationStatus::Approved])->save();

        $application->update(['description' => 'Changed afterwards']);
    })->throws(DomainException::class, 'approved application cannot be changed');

    it('never changes its number or beneficiary', function (array $change) {
        $application = Application::factory()->create();

        expect(fn () => $application->forceFill($change)->save())->toThrow(DomainException::class);
    })->with([
        'number' => [['application_no' => 'AP-1999-000001']],
        'beneficiary' => [fn () => ['beneficiary_id' => Beneficiary::factory()->create()->id]],
    ]);

    it('knows which statuses are open', function () {
        expect(ApplicationStatus::Submitted->isOpen())->toBeTrue()
            ->and(ApplicationStatus::Verifying->isOpen())->toBeTrue()
            ->and(ApplicationStatus::Approved->isOpen())->toBeFalse()
            ->and(ApplicationStatus::Rejected->isOpen())->toBeFalse();
    });
});

describe('verification visits', function () {
    it('records a visit for an open application', function () {
        $visit = VerificationVisit::factory()->create();

        expect($visit->application->visits)->toHaveCount(1)
            ->and($visit->zakat_eligible)->toBeTrue();
    });

    it('lists the latest visit first', function () {
        $application = Application::factory()->create();
        $older = VerificationVisit::factory()->for($application)->create(['visit_date' => today()->subDays(5)]);
        $newer = VerificationVisit::factory()->for($application)->create(['visit_date' => today()]);

        expect($application->visits->pluck('id')->all())->toBe([$newer->id, $older->id]);
    });

    it('refuses a visit for a decided application', function () {
        $application = Application::factory()->create();
        $application->forceFill(['status' => ApplicationStatus::Rejected])->save();

        VerificationVisit::factory()->for($application)->create();
    })->throws(DomainException::class, 'open applications');

    it('checks the visit details', function (array $attributes, string $message) {
        expect(fn () => VerificationVisit::factory()->create($attributes))->toThrow(DomainException::class, $message);
    })->with([
        'future date' => [['visit_date' => today()->addDay()], 'cannot be in the future'],
        'blank findings' => [['findings' => ' '], 'what was found'],
        'empty household' => [['household_size_seen' => 0], 'at least one person'],
        'negative income' => [['monthly_income_found' => '-5'], 'cannot be negative'],
    ]);

    it('keeps photos and documents on the private disk', function () {
        expect((new VerificationVisit)->getMediaCollection(VerificationVisit::PHOTOS)?->diskName)->toBe('local')
            ->and((new Beneficiary)->getMediaCollection(Beneficiary::DOCUMENTS)?->diskName)->toBe('local');
    });
});
