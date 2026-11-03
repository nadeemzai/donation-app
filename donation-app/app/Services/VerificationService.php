<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\VisitRecommendation;
use App\Models\Application;
use App\Models\Beneficiary;
use App\Models\User;
use App\Models\VerificationVisit;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Verification visits and the Zakat-eligibility decision (Week 9 Day 4, FR-BEN-03).
 *
 *   recordVisit() → a field officer's findings, photos, recommendation and Zakat view.
 *                   A "submitted" application moves to "being verified". What was seen
 *                   can update the household's size and income.
 *   decideZakat() → the decision itself, on the household, with a reason. Only this
 *                   lets an approval use a Zakat fund (ApplicationWorkflowService).
 *
 * Visits are not edited afterwards — a correction is a new visit. Every step is audited.
 */
class VerificationService
{
    public const MAX_PHOTOS = 10;

    /**
     * @param  array<string, mixed>  $data  visit_date, findings, recommendation, officer_id?, household_size_seen?,
     *                                      monthly_income_found?, zakat_eligible? (bool|null), zakat_reason?,
     *                                      update_household? (bool), photos? (paths on the private "local" disk)
     */
    public function recordVisit(Application $application, array $data, ?User $by = null): VerificationVisit
    {
        $photos = array_values(array_filter((array) ($data['photos'] ?? []), fn (mixed $p): bool => is_string($p) && $p !== ''));

        if (count($photos) > self::MAX_PHOTOS) {
            throw new DomainException('Attach at most '.self::MAX_PHOTOS.' photos to one visit.');
        }

        $recommendation = $this->recommendation($data['recommendation'] ?? null);
        $zakatEligible = $this->optionalBool($data['zakat_eligible'] ?? null);
        $zakatReason = $this->optionalText($data['zakat_reason'] ?? null);

        if ($zakatEligible !== null && $zakatReason === null) {
            throw new DomainException('Write why you think the household is (or is not) Zakat-eligible.');
        }

        $sizeSeen = filled($data['household_size_seen'] ?? null) ? (int) $data['household_size_seen'] : null;
        $incomeFound = $this->optionalIncome($data['monthly_income_found'] ?? null);

        $visit = DB::transaction(function () use ($application, $data, $by, $recommendation, $zakatEligible, $zakatReason, $sizeSeen, $incomeFound): VerificationVisit {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);

            if (! $locked->isOpen()) {
                throw new DomainException("This application is {$locked->status->label()} — visits can only be recorded while it is open.");
            }

            if ($locked->beneficiary->trashed()) {
                throw new DomainException('This beneficiary is archived.');
            }

            $officerId = filled($data['officer_id'] ?? null) ? (int) $data['officer_id'] : $by?->id;

            if ($locked->status === ApplicationStatus::Submitted) {
                $locked->forceFill(['status' => ApplicationStatus::Verifying])->save();
                $this->audit($locked, $by, 'verification_started', "Application {$locked->application_no} sent for verification (visit recorded)");
            }

            if ($locked->assigned_officer_id === null && $officerId !== null) {
                $locked->forceFill(['assigned_officer_id' => $officerId])->save();
            }

            $visit = VerificationVisit::query()->create([
                'application_id' => $locked->id,
                'officer_id' => $officerId,
                'visit_date' => Carbon::parse((string) ($data['visit_date'] ?? today()->toDateString()))->toDateString(),
                'findings' => (string) ($data['findings'] ?? ''),
                'household_size_seen' => $sizeSeen,
                'monthly_income_found' => $incomeFound,
                'recommendation' => $recommendation,
                'zakat_eligible' => $zakatEligible,
                'zakat_reason' => $zakatReason,
            ]);

            if ((bool) ($data['update_household'] ?? false)) {
                $this->updateHousehold($locked->beneficiary, $sizeSeen, $incomeFound, $by, $locked);
            }

            $this->audit($locked, $by, 'visit_recorded', "Verification visit recorded for {$locked->application_no}: {$recommendation->label()}", [
                'visit_id' => $visit->id,
                'recommendation' => $recommendation->value,
                'zakat_eligible' => $zakatEligible,
            ]);

            return $visit;
        });

        // Files move only after the visit is safely saved.
        foreach ($photos as $path) {
            if (! Storage::disk('local')->exists($path)) {
                continue;
            }

            $visit->addMediaFromDisk($path, 'local')->toMediaCollection(VerificationVisit::PHOTOS);
        }

        return $visit->refresh();
    }

    /**
     * The household's Zakat eligibility. Needs a reason; the officer's view from the visit
     * is advice, this is the decision.
     */
    public function decideZakat(Beneficiary $beneficiary, bool $eligible, string $reason, ?User $by = null): Beneficiary
    {
        $reason = $this->optionalText($reason) ?? throw new DomainException('Write the reason for the Zakat decision.');

        DB::transaction(function () use ($beneficiary, $eligible, $reason, $by): void {
            $locked = Beneficiary::withTrashed()->lockForUpdate()->findOrFail($beneficiary->id);

            if ($locked->trashed()) {
                throw new DomainException('This beneficiary is archived.');
            }

            $was = $locked->zakat_decided_at !== null ? $locked->is_zakat_eligible : null;

            $locked->forceFill([
                'is_zakat_eligible' => $eligible,
                'zakat_decided_at' => now(),
                'zakat_decided_by' => $by?->id,
                'zakat_note' => $reason,
            ])->save();

            activity('audit')
                ->performedOn($locked)
                ->causedBy($by)
                ->event('zakat_decided')
                ->withProperties(['eligible' => $eligible, 'previous' => $was, 'reason' => $reason])
                ->log("Zakat eligibility for {$locked->code}: ".($eligible ? 'eligible' : 'not eligible'));
        });

        return $beneficiary->refresh();
    }

    private function updateHousehold(Beneficiary $beneficiary, ?int $sizeSeen, ?string $incomeFound, ?User $by, Application $application): void
    {
        $changes = [];

        if ($sizeSeen !== null && $sizeSeen !== $beneficiary->household_size) {
            $changes['household_size'] = $sizeSeen;
            $changes['dependents'] = min($beneficiary->dependents, $sizeSeen);
        }

        if ($incomeFound !== null && bccomp($incomeFound, (string) $beneficiary->monthly_income, 2) !== 0) {
            $changes['monthly_income'] = $incomeFound;
        }

        if ($changes === []) {
            return;
        }

        $beneficiary->update($changes);

        $this->audit($application, $by, 'household_updated', "Household {$beneficiary->code} updated from the verification visit", $changes);
    }

    private function recommendation(mixed $given): VisitRecommendation
    {
        if ($given instanceof VisitRecommendation) {
            return $given;
        }

        return VisitRecommendation::tryFrom(is_string($given) ? $given : '')
            ?? throw new DomainException('Choose a recommendation.');
    }

    private function optionalBool(mixed $value): ?bool
    {
        return match (true) {
            is_bool($value) => $value,
            $value === null, $value === '' => null,
            default => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
        };
    }

    private function optionalIncome(mixed $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        try {
            return Money::normalise(trim((string) $amount)); // 0 is allowed: no income
        } catch (InvalidArgumentException) {
            throw new DomainException('Enter the income in rupees, e.g. 15000.');
        }
    }

    private function optionalText(mixed $text): ?string
    {
        $text = trim(is_string($text) ? $text : '');

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function audit(Application $application, ?User $by, string $event, string $message, array $properties = []): void
    {
        activity('audit')
            ->performedOn($application)
            ->causedBy($by)
            ->event($event)
            ->withProperties([...$properties, 'status' => $application->status->value])
            ->log($message);
    }
}
