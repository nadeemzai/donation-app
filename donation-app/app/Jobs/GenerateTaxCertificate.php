<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Donor;
use App\Models\User;
use App\Services\TaxCertificateService;
use App\Support\FiscalYear;
use DomainException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** One donor's certificate for one fiscal year, in the background (FR-RCP-03). */
class GenerateTaxCertificate implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 120; // headless Chrome

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $uniqueFor = 900;

    public function __construct(public int $donorId, public int $fiscalYear, public ?int $userId = null) {}

    public function uniqueId(): string
    {
        return $this->donorId.'-'.$this->fiscalYear;
    }

    public function handle(TaxCertificateService $certificates): void
    {
        $donor = Donor::withTrashed()->find($this->donorId);

        if ($donor === null) {
            return;
        }

        try {
            $certificates->generate($donor, new FiscalYear($this->fiscalYear), $this->userId !== null ? User::query()->find($this->userId) : null);
        } catch (DomainException) {
            // Not eligible / nothing to certify any more — nothing to retry.
        }
    }
}
