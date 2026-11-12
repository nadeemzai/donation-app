<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DonationStatus;
use App\Enums\SequenceKey;
use App\Models\Donation;
use App\Models\Receipt;
use App\Models\User;
use App\Settings\GeneralSettings;
use App\Support\AmountInWords;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Donation receipts (FR-RCP-01, BR-04).
 *
 *   issue()  → one receipt per paid donation: RC number + snapshot, then the PDF.
 *              Calling it again returns the same receipt (safe from jobs and buttons).
 *   render() → (re)draws the PDF from the snapshot — same content every time.
 *   void()   → marks the receipt void (e.g. donation fully refunded); never deleted.
 *
 * PDFs are rendered by headless Chrome (spatie/laravel-pdf + Browsershot) so Urdu
 * names and text are shaped correctly. Files go to the private "local" disk.
 */
class ReceiptService
{
    public const DISK = 'local';

    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly GeneralSettings $org,
    ) {}

    public function issue(Donation $donation, ?User $by = null): Receipt
    {
        $receipt = DB::transaction(function () use ($donation, $by): Receipt {
            $locked = Donation::query()->lockForUpdate()->findOrFail($donation->getKey());

            $existing = Receipt::query()->where('donation_id', $locked->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            if (! in_array($locked->status, [DonationStatus::Paid, DonationStatus::PartiallyRefunded], true)) {
                throw new DomainException("Donation {$locked->donation_no} is {$locked->status->label()}. Only a paid donation gets a receipt.");
            }

            $receipt = Receipt::query()->create([
                'receipt_no' => $this->numbers->next(SequenceKey::Receipt),
                'donation_id' => $locked->id,
                'amount' => (string) $locked->amount,
                'currency' => $locked->currency,
                'snapshot' => $this->snapshot($locked),
                'issued_at' => now(),
                'issued_by' => $by?->getKey(),
                'disk' => self::DISK,
            ]);

            activity('audit')->performedOn($receipt)->causedBy($by)->event('issued')
                ->withProperties(['donation_id' => $locked->id])
                ->log("Receipt {$receipt->receipt_no} issued for donation {$locked->donation_no}");

            return $receipt;
        });

        // Outside the transaction: Chrome can be slow. If rendering fails the receipt
        // (and its number) stays; render() can be retried.
        return $receipt->isRendered() ? $receipt : $this->render($receipt);
    }

    public function render(Receipt $receipt): Receipt
    {
        $content = Pdf::view('pdf.receipt', ['r' => $receipt->snapshot, 'receiptNo' => $receipt->receipt_no, 'void' => $receipt->isVoid()])
            ->format('a4')
            ->margins(12, 12, 12, 12)
            ->withBrowsershot(fn ($browsershot) => $browsershot->waitUntilNetworkIdle()) // let the Urdu web font load
            ->generatePdfContent();

        $path = 'receipts/'.$receipt->issued_at->format('Y').'/'.$receipt->fileName();
        Storage::disk($receipt->disk)->put($path, $content);

        $receipt->path = $path;
        $receipt->file_hash = hash('sha256', $content);
        $receipt->rendered_at = now();
        $receipt->render_count++;
        $receipt->save();

        return $receipt;
    }

    public function void(Receipt $receipt, string $reason, ?User $by = null): Receipt
    {
        if ($receipt->isVoid()) {
            return $receipt;
        }

        $receipt->voided_at = now();
        $receipt->void_reason = mb_substr($reason, 0, 255);
        $receipt->save();

        activity('audit')->performedOn($receipt)->causedBy($by)->event('voided')
            ->withProperty('reason', $reason)
            ->log("Receipt {$receipt->receipt_no} voided: {$reason}");

        return $receipt->isRendered() ? $this->render($receipt) : $receipt; // PDF now says VOID
    }

    /**
     * The stored PDF, for WhatsApp / email attachments.
     *
     * @return array{disk: string, path: string, name: string}
     */
    public function file(Receipt $receipt): array
    {
        if (! $receipt->isRendered()) {
            $receipt = $this->render($receipt);
        }

        return ['disk' => $receipt->disk, 'path' => (string) $receipt->path, 'name' => $receipt->fileName()];
    }

    /**
     * Everything the receipt shows, frozen at issue time.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Donation $donation): array
    {
        $donation->loadMissing(['donor', 'fund', 'campaign']);
        $donor = $donation->donor;

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
                'footer' => $this->org->receipt_footer_text,
                'logo' => $this->logoDataUri(),
            ],
            'donation' => [
                'no' => $donation->donation_no,
                'amount' => (string) $donation->amount,
                'amount_display' => Money::rupees((string) $donation->amount),
                'amount_words' => AmountInWords::rupees((string) $donation->amount),
                'currency' => $donation->currency,
                'paid_at' => ($donation->paid_at ?? now())->format('d M Y'),
                'method' => $donation->payment_method->label(),
                'reference' => $donation->reference,
                'dedication' => $donation->dedication,
            ],
            'donor' => [
                'name' => $donor->name, // the receipt goes to the donor, so the real name even if anonymous (BR-08)
                'code' => $donor->donor_code,
                'cnic' => $donor->maskedCnic(),
                'ntn' => $donor->maskedNtn(),
                'city' => $donor->city,
            ],
            'fund' => [
                'name' => $donation->fund->name,
                'is_zakat' => $donation->fund->is_zakat,
            ],
            'campaign' => $donation->campaign?->title,
        ];
    }

    /** The organisation logo embedded in the PDF (no network needed), if one is uploaded. */
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
