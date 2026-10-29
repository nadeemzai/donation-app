<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Donation;
use App\Models\MessageTemplate;
use App\Models\Receipt;
use App\Models\TaxCertificate;
use App\Settings\GeneralSettings;
use App\Support\Money;

/**
 * Fills a template's placeholders (FR-NTF-02). Picks the Urdu text when the donor prefers
 * Urdu and the template has one; otherwise English. Unknown or missing placeholders become
 * empty text — the edit screen already refuses unknown ones.
 */
class TemplateRenderer
{
    public function __construct(private readonly GeneralSettings $org) {}

    /**
     * @param  array<string, string|null>  $vars
     * @return array{whatsapp: string, email_subject: string, email_body: string}
     */
    public function render(MessageTemplate $template, array $vars, bool $urdu = false): array
    {
        $pick = fn (?string $urduText, string $english): string => $urdu && filled($urduText) ? (string) $urduText : $english;

        return [
            'whatsapp' => $this->fill($pick($template->whatsapp_body_ur, $template->whatsapp_body), $vars),
            'email_subject' => $this->fill($pick($template->email_subject_ur, $template->email_subject), $vars),
            'email_body' => $this->fill($pick($template->email_body_ur, $template->email_body), $vars),
        ];
    }

    /**
     * Placeholder values for a donation (and its receipt, if issued).
     *
     * @return array<string, string|null>
     */
    public function varsFor(Donation $donation, ?Receipt $receipt = null, ?string $reason = null): array
    {
        $donation->loadMissing(['donor', 'fund', 'campaign']);

        return [
            'donor_name' => $donation->donor->name,
            'amount' => Money::rupees((string) $donation->amount),
            'donation_no' => $donation->donation_no,
            'receipt_no' => $receipt?->receipt_no,
            'fund' => $donation->fund->name,
            'campaign' => $donation->campaign?->title,
            'date' => ($donation->paid_at ?? $donation->created_at ?? now())->format('d M Y'),
            'reason' => $reason,
            'org_name' => $this->org->org_legal_name ?: $this->org->org_name,
        ];
    }

    /**
     * Placeholder values for an annual tax certificate.
     *
     * @return array<string, string|null>
     */
    public function varsForCertificate(TaxCertificate $certificate): array
    {
        return [
            'donor_name' => $certificate->donor->name,
            'amount' => Money::rupees((string) $certificate->total),
            'certificate_no' => $certificate->certificate_no,
            'fiscal_year' => $certificate->fiscalYear()->label(),
            'org_name' => $this->org->org_legal_name ?: $this->org->org_name,
        ];
    }

    /**
     * Sample values for the preview on the edit screen.
     *
     * @return array<string, string|null>
     */
    public function sampleVars(): array
    {
        return [
            'donor_name' => 'Ayesha Siddiqui',
            'amount' => 'Rs 12,500.00',
            'donation_no' => 'DN-'.now()->year.'-000123',
            'receipt_no' => 'RC-'.now()->year.'-000098',
            'fund' => 'Zakat Fund',
            'campaign' => 'Winter Relief',
            'date' => now()->format('d M Y'),
            'reason' => 'Amount on the slip does not match',
            'org_name' => $this->org->org_legal_name ?: $this->org->org_name,
            'fiscal_year' => '2025-26',
            'certificate_no' => 'TC-'.now()->year.'-000017',
        ];
    }

    /**
     * @param  array<string, string|null>  $vars
     */
    private function fill(string $text, array $vars): string
    {
        $replacements = [];

        foreach (array_keys(MessageTemplate::PLACEHOLDERS) as $name) {
            $replacements['{'.$name.'}'] = (string) ($vars[$name] ?? '');
        }

        return trim(strtr($text, $replacements));
    }
}
