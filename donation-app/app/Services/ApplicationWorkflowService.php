<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationCategory;
use App\Enums\ApplicationStatus;
use App\Enums\BeneficiaryStatus;
use App\Enums\VisitRecommendation;
use App\Models\Application;
use App\Models\Beneficiary;
use App\Models\Fund;
use App\Models\User;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Applications for help (Week 9 Day 3, FR-BEN-02). The only place an application changes status.
 *
 *   submit()            → submitted (one open application per category per household)
 *   startVerification() → verifying, with a field officer to visit
 *   approve()           → approved: needs a verification visit; a Zakat fund needs a
 *                         Zakat-eligible household; the household becomes "verified"
 *   reject()            → rejected, with a reason the next reviewer can read
 *   reopen()            → back to verifying when new facts come in
 *
 * Every step runs in a transaction on a locked row and is written to the audit log.
 */
class ApplicationWorkflowService
{
    /**
     * @param  array<string, mixed>  $attributes  beneficiary_id, category, description, requested_amount?, fund_id?, assigned_officer_id?
     */
    public function submit(array $attributes, ?User $by = null): Application
    {
        return DB::transaction(function () use ($attributes, $by): Application {
            $beneficiary = Beneficiary::query()->lockForUpdate()->find($attributes['beneficiary_id'] ?? null)
                ?? throw new DomainException('Choose the beneficiary this application is for.');

            if ($beneficiary->status === BeneficiaryStatus::Inactive) {
                throw new DomainException('This beneficiary is inactive. Make them active again before a new application.');
            }

            $given = $attributes['category'] ?? null;
            $category = $given instanceof ApplicationCategory
                ? $given
                : (ApplicationCategory::tryFrom(is_string($given) ? $given : '') ?? throw new DomainException('Choose what kind of help is needed.'));

            $alreadyOpen = $beneficiary->applications()
                ->where('category', $category->value)
                ->whereIn('status', [ApplicationStatus::Submitted->value, ApplicationStatus::Verifying->value])
                ->value('application_no');

            if ($alreadyOpen !== null) {
                throw new DomainException("{$beneficiary->name} already has an open {$category->label()} application ({$alreadyOpen}).");
            }

            $this->assertUsableFund($attributes['fund_id'] ?? null);

            $application = new Application([
                'beneficiary_id' => $beneficiary->id,
                'category' => $category,
                'description' => $attributes['description'] ?? '',
                'requested_amount' => $this->optionalAmount($attributes['requested_amount'] ?? null),
                'fund_id' => filled($attributes['fund_id'] ?? null) ? (int) $attributes['fund_id'] : null,
                'assigned_officer_id' => filled($attributes['assigned_officer_id'] ?? null)
                    ? (int) $attributes['assigned_officer_id']
                    : $beneficiary->assigned_officer_id,
                'created_by' => $by?->id,
            ]);
            $application->save();

            $this->audit($application, $by, 'submitted', "Application {$application->application_no} submitted for {$beneficiary->code}");

            return $application;
        });
    }

    /** Submitted (or re-opened) → being verified, with the officer who will visit. */
    public function startVerification(Application $application, ?int $officerId = null, ?User $by = null): Application
    {
        return $this->change($application, function (Application $locked) use ($officerId, $by): void {
            $this->assertCanMove($locked, ApplicationStatus::Verifying);

            $locked->forceFill([
                'status' => ApplicationStatus::Verifying,
                'assigned_officer_id' => $officerId ?? $locked->assigned_officer_id ?? $locked->beneficiary->assigned_officer_id ?? $by?->id,
            ])->save();

            $this->audit($locked, $by, 'verification_started', "Application {$locked->application_no} sent for verification");
        });
    }

    public function approve(Application $application, ?string $amount = null, ?string $note = null, ?User $by = null): Application
    {
        return $this->change($application, function (Application $locked) use ($amount, $note, $by): void {
            $this->assertCanMove($locked, ApplicationStatus::Approved);

            $beneficiary = $locked->beneficiary;

            if ($beneficiary->trashed() || $beneficiary->status === BeneficiaryStatus::Inactive) {
                throw new DomainException('This beneficiary is archived or inactive — an application cannot be approved.');
            }

            $latestVisit = $locked->visits()->first();

            if ($latestVisit === null) {
                throw new DomainException('Record a verification visit before approving.');
            }

            $note = $this->optionalText($note);

            if ($latestVisit->recommendation !== VisitRecommendation::Approve && $note === null) {
                throw new DomainException('The last visit did not recommend approval. Write why you are approving anyway.');
            }

            $fund = $locked->fund_id !== null ? Fund::query()->find($locked->fund_id) : null;

            if ($fund !== null && $fund->is_zakat && ! $beneficiary->is_zakat_eligible) {
                throw new DomainException("{$fund->name} is a Zakat fund and this household has not been found Zakat-eligible.");
            }

            $approved = $this->optionalAmount($amount) ?? $locked->requested_amount;

            $locked->forceFill([
                'status' => ApplicationStatus::Approved,
                'approved_amount' => $approved,
                'decided_at' => now(),
                'decided_by' => $by?->id,
                'decision_note' => $note,
            ])->save();

            if ($beneficiary->status === BeneficiaryStatus::Registered) {
                $beneficiary->forceFill(['status' => BeneficiaryStatus::Verified])->save();
            }

            $this->audit($locked, $by, 'approved', "Application {$locked->application_no} approved"
                .($approved !== null ? ' for '.Money::rupees((string) $approved) : ''), ['approved_amount' => $approved]);
        });
    }

    public function reject(Application $application, string $reason, ?User $by = null): Application
    {
        return $this->change($application, function (Application $locked) use ($reason, $by): void {
            $this->assertCanMove($locked, ApplicationStatus::Rejected);

            $reason = $this->optionalText($reason) ?? throw new DomainException('Write why the application is rejected.');

            $locked->forceFill([
                'status' => ApplicationStatus::Rejected,
                'decided_at' => now(),
                'decided_by' => $by?->id,
                'decision_note' => $reason,
            ])->save();

            $this->audit($locked, $by, 'rejected', "Application {$locked->application_no} rejected", ['reason' => $reason]);
        });
    }

    /** Rejected → being verified again. The old decision is kept in the audit log. */
    public function reopen(Application $application, string $reason, ?User $by = null): Application
    {
        return $this->change($application, function (Application $locked) use ($reason, $by): void {
            if ($locked->status !== ApplicationStatus::Rejected) {
                throw new DomainException('Only a rejected application can be re-opened.');
            }

            $reason = $this->optionalText($reason) ?? throw new DomainException('Write what has changed.');

            $locked->forceFill([
                'status' => ApplicationStatus::Verifying,
                'decided_at' => null,
                'decided_by' => null,
                'decision_note' => null,
            ])->save();

            $this->audit($locked, $by, 'reopened', "Application {$locked->application_no} re-opened", ['reason' => $reason]);
        });
    }

    /**
     * @param  callable(Application): void  $step
     */
    private function change(Application $application, callable $step): Application
    {
        DB::transaction(function () use ($application, $step): void {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);
            $step($locked);
        });

        return $application->refresh();
    }

    private function assertCanMove(Application $application, ApplicationStatus $next): void
    {
        if (! $application->status->canTransitionTo($next)) {
            throw new DomainException("This application is {$application->status->label()} — it cannot be moved to {$next->label()}.");
        }
    }

    private function assertUsableFund(mixed $fundId): void
    {
        if (blank($fundId)) {
            return;
        }

        $fund = Fund::query()->find($fundId);

        if ($fund === null || ! $fund->is_active) {
            throw new DomainException('Choose an active fund, or leave it empty.');
        }
    }

    private function optionalAmount(mixed $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        try {
            $normalised = Money::normalise(trim((string) $amount));
        } catch (InvalidArgumentException) {
            throw new DomainException('Enter the amount in rupees, e.g. 15000 or 15000.50.');
        }

        if (bccomp($normalised, '0', 2) <= 0) {
            throw new DomainException('The amount must be more than zero.');
        }

        return $normalised;
    }

    private function optionalText(?string $text): ?string
    {
        $text = trim((string) $text);

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
