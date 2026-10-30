<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\TaxCertificate;
use App\Support\ApiFormat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of the logged-in donor's tax certificates (one per tax year).
 *
 * @mixin TaxCertificate
 */
class TaxCertificateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TaxCertificate $certificate */
        $certificate = $this->resource;

        return [
            'id' => $certificate->id,
            'number' => $certificate->certificate_no,
            'tax_year' => $certificate->fiscalYear()->label(),
            'total' => ApiFormat::money((string) $certificate->total),
            'zakat_total' => ApiFormat::money((string) $certificate->zakat_total),
            'donation_count' => $certificate->donation_count,
            'issued_at' => ApiFormat::time($certificate->issued_at),
            'download_url' => route('api.v1.me.tax-certificates.download', $certificate->id),
        ];
    }
}
