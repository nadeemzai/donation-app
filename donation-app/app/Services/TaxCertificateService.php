<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SequenceKey;
use App\Jobs\GenerateTaxCertificate;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\TaxCertificate;
use App\Models\User;
use App\Services\Messaging\NotificationService;
use App\Services\Messaging\TemplateRenderer;
use App\Settings\GeneralSettings;
use App\Support\AmountInWords;
use App\Support\FiscalYear;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Annual tax certificates (FR-RCP-03, BR-12).
 *
 * - One valid certificate per donor per fiscal year (1 July – 30 June, Pakistan time).
 * - Only for donors with a CNIC or NTN on file (BR-12).
 * - Counts paid donations after refunds; fully refunded ones are left out.
 * - generate() again with the same donations returns the same certificate; if donations
 *   changed (a refund, a late confirmation) the old one is voided and a new one issued.
 * - Finance checks them, then sends: send() uses the "tax_certificate" message template.
 */
class TaxCertificateService
{
    public const DISK = 'local';

    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly GeneralSettings $org,
        private readonly NotificationService $notifications,
        private readonly TemplateRenderer $renderer,
    ) {}

    /** Null when the donor can get a certificate, otherwise the reason they can't. */
    public function ineligibleReason(Donor $donor): ?string
    {
        return blank($donor->cnic) && blank($donor->ntn)
            ? 'No CNIC or NTN on file (BR-12).'
            : null;
    }

    /**
     * Donations that count for the year: paid or partly refunded, paid inside the year,
     * with something left after refunds. Oldest first.
     *
     * @return Collection<int, Donation>
     */
    public function linesFor(Donor $donor, FiscalYear $year): Collection
    {
        $tz = (string) config('app.timezone');

        return Donation::query()
            ->counted()
            ->where('donor_id', $donor->id)
            ->whereBetween('paid_at', [$year->start()->setTimezone($tz), $year->end()->setTimezone($tz)])
            ->with(['fund', 'receipt'])
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Donation $d): bool => bccomp($d->netAmount(), '0', 2) > 0)
            ->values();
    }

    public function generate(Donor $donor, FiscalYear $year, ?User $by = null): TaxCertificate
    {
        $reason = $this->ineligibleReason($donor);
        if ($reason !== null) {
            throw new DomainException("{$donor->name}: {$reason}");
        }

        /** @var array{0: TaxCertificate, 1: TaxCertificate|null} $result */
        $result = DB::transaction(function () use ($donor, $year, $by): array {
            // One generation per donor at a time.
            Donor::withTrashed()->lockForUpdate()->findOrFail($donor->id);

            $lines = $this->linesFor($donor, $year);
            if ($lines->isEmpty()) {
                throw new DomainException("{$donor->name} has no paid donations in FY {$year->label()}.");
            }

            $hash = hash('sha256', $lines->map(fn (Donation $d): string => $d->donation_no.'|'.$d->netAmount())->implode(','));

            $current = TaxCertificate::query()->valid()
                ->where('donor_id', $donor->id)
                ->where('fiscal_year', $year->startYear)
                ->first();

            if ($current !== null && $current->lines_hash === $hash) {
                return [$current, null]; // nothing changed
            }

            $replaced = $current !== null ? $this->markVoid($current, 'Reissued: the donations for the year changed', $by) : null;

            $total = $lines->reduce(fn (string $sum, Donation $d): string => bcadd($sum, $d->netAmount(), 2), '0.00');
            $zakat = $lines->filter(fn (Donation $d): bool => $d->fund->is_zakat)
                ->reduce(fn (string $sum, Donation $d): string => bcadd($sum, $d->netAmount(), 2), '0.00');

            $certificate = TaxCertificate::query()->create([
                'certificate_no' => $this->numbers->next(SequenceKey::TaxCertificate),
                'donor_id' => $donor->id,
                'fiscal_year' => $year->startYear,
                'total' => $total,
                'zakat_total' => $zakat,
                'donation_count' => $lines->count(),
                'lines_hash' => $hash,
                'snapshot' => $this->snapshot($donor, $year, $lines, $total, $zakat),
                'issued_at' => now(),
                'issued_by' => $by?->getKey(),
                'disk' => self::DISK,
            ]);

            activity('audit')->performedOn($certificate)->causedBy($by)->event('issued')
                ->withProperties(['fiscal_year' => $year->label(), 'total' => $total, 'replaces' => $current?->certificate_no])
                ->log("Tax certificate {$certificate->certificate_no} issued to {$donor->name} for FY {$year->label()}");

            return [$certificate, $replaced];
        });

        [$certificate, $replaced] = $result;

        // Chrome runs outside the transaction.
        if ($replaced !== null && $replaced->isRendered()) {
            $this->render($replaced); // now marked VOID
        }

        return $certificate->isRendered() ? $certificate : $this->render($certificate);
    }

    /**
     * Queue a certificate for every eligible donor who gave in the year.
     *
     * @return array{queued: int, skipped: array<int, string>} skipped = donor id => "Name (code): reason"
     */
    public function generateForYear(FiscalYear $year, ?User $by = null): array
    {
        $tz = (string) config('app.timezone');

        $donorIds = Donation::query()
            ->counted()
            ->whereBetween('paid_at', [$year->start()->setTimezone($tz), $year->end()->setTimezone($tz)])
            ->distinct()
            ->pluck('donor_id');

        $queued = 0;
        $skipped = [];

        foreach (Donor::withTrashed()->whereIn('id', $donorIds)->orderBy('name')->get() as $donor) {
            $reason = $this->ineligibleReason($donor);

            if ($reason !== null) {
                $skipped[$donor->id] = "{$donor->name} ({$donor->donor_code}): {$reason}";

                continue;
            }

            GenerateTaxCertificate::dispatch($donor->id, $year->startYear, $by?->id);
            $queued++;
        }

        activity('audit')->causedBy($by)->event('tax_certificates_generated')
            ->withProperties(['fiscal_year' => $year->label(), 'queued' => $queued, 'skipped' => array_values($skipped)])
            ->log("Tax certificates for FY {$year->label()}: {$queued} queued, ".count($skipped).' skipped');

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    public function render(TaxCertificate $certificate): TaxCertificate
    {
        $content = Pdf::view('pdf.tax-certificate', [
            'c' => $certificate->snapshot,
            'certificateNo' => $certificate->certificate_no,
            'void' => $certificate->isVoid(),
        ])
            ->format('a4')
            ->margins(12, 12, 12, 12)
            ->withBrowsershot(fn ($browsershot) => $browsershot->waitUntilNetworkIdle())
            ->generatePdfContent();

        $path = 'tax-certificates/'.$certificate->fiscal_year.'/'.$certificate->fileName();
        Storage::disk($certificate->disk)->put($path, $content);

        $certificate->path = $path;
        $certificate->file_hash = hash('sha256', $content);
        $certificate->rendered_at = now();
        $certificate->save();

        return $certificate;
    }

    public function void(TaxCertificate $certificate, string $reason, ?User $by = null): TaxCertificate
    {
        if ($certificate->isVoid()) {
            return $certificate;
        }

        $this->markVoid($certificate, $reason, $by);

        return $certificate->isRendered() ? $this->render($certificate) : $certificate;
    }

    /**
     * Send to the donor on WhatsApp / email with the PDF (Finance's "Send to donors").
     *
     * @return list<MessageLog>
     */
    public function send(TaxCertificate $certificate, ?User $by = null): array
    {
        if ($certificate->isVoid()) {
            throw new DomainException("{$certificate->certificate_no} is void — send the reissued certificate instead.");
        }

        $logs = $this->notifications->send(
            $certificate->donor,
            MessageTemplate::TAX_CERTIFICATE,
            $this->renderer->varsForCertificate($certificate),
            $certificate,
            $this->file($certificate),
        );

        $certificate->sent_at = now();
        $certificate->save();

        activity('audit')->performedOn($certificate)->causedBy($by)->event('sent')
            ->log("Tax certificate {$certificate->certificate_no} sent to {$certificate->donor->name}");

        return $logs;
    }

    /**
     * @return array{disk: string, path: string, name: string}
     */
    public function file(TaxCertificate $certificate): array
    {
        if (! $certificate->isRendered()) {
            $certificate = $this->render($certificate);
        }

        return ['disk' => $certificate->disk, 'path' => (string) $certificate->path, 'name' => $certificate->fileName()];
    }

    private function markVoid(TaxCertificate $certificate, string $reason, ?User $by): TaxCertificate
    {
        $certificate->voided_at = now();
        $certificate->void_reason = mb_substr($reason, 0, 255);
        $certificate->save();

        activity('audit')->performedOn($certificate)->causedBy($by)->event('voided')
            ->withProperty('reason', $reason)
            ->log("Tax certificate {$certificate->certificate_no} voided: {$reason}");

        return $certificate;
    }

    /**
     * Everything the certificate shows, frozen at issue. CNIC / NTN are stored masked —
     * full identity numbers never go into this JSON (they stay encrypted on the donor).
     *
     * @param  Collection<int, Donation>  $lines
     * @return array<string, mixed>
     */
    private function snapshot(Donor $donor, FiscalYear $year, Collection $lines, string $total, string $zakat): array
    {
        return [
            'org' => [
                'name' => $this->org->org_name,
                'legal_name' => $this->org->org_legal_name,
                'registration_no' => $this->org->registration_no,
                'tax_exemption_ref' => $this->org->tax_exemption_ref,
                'address' => $this->org->address,
                'phone' => $this->org->phone,
                'email' => $this->org->email,
                'website' => $this->org->website,
                'logo' => $this->logoDataUri(),
            ],
            'donor' => [
                'name' => $donor->name,
                'code' => $donor->donor_code,
                'cnic' => $donor->maskedCnic(),
                'ntn' => $donor->maskedNtn(),
                'city' => $donor->city,
            ],
            'year' => [
                'label' => $year->label(),
                'from' => $year->start()->format('d M Y'),
                'to' => $year->end()->format('d M Y'),
            ],
            'lines' => $lines->map(fn (Donation $d): array => [
                'date' => $d->paid_at?->format('d M Y'),
                'donation_no' => $d->donation_no,
                'receipt_no' => $d->receipt?->receipt_no,
                'fund' => $d->fund->name,
                'zakat' => $d->fund->is_zakat,
                'amount' => Money::rupees($d->netAmount()),
            ])->all(),
            'total' => $total,
            'total_display' => Money::rupees($total),
            'total_words' => AmountInWords::rupees($total),
            'zakat_display' => bccomp($zakat, '0', 2) > 0 ? Money::rupees($zakat) : null,
        ];
    }

    private function logoDataUri(): ?string
    {
        $path = $this->org->logo_path;

        if ($path === null || $path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) Storage::disk('public')->get($path));
    }
}
