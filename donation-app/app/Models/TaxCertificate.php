<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\FiscalYear;
use Database\Factories\TaxCertificateFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Annual tax certificate for one donor and one fiscal year (FR-RCP-03, BR-12).
 * Issued only by TaxCertificateService. What it says never changes; a corrected
 * certificate is a new one and the old one is voided. Never deleted.
 *
 * @property int $id
 * @property string $certificate_no
 * @property int $donor_id
 * @property int $fiscal_year
 * @property string $total
 * @property string $zakat_total
 * @property int $donation_count
 * @property string $lines_hash
 * @property array<string, mixed> $snapshot
 * @property Carbon $issued_at
 * @property int|null $issued_by
 * @property string $disk
 * @property string|null $path
 * @property string|null $file_hash
 * @property Carbon|null $rendered_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Donor $donor
 */
#[Fillable(['certificate_no', 'donor_id', 'fiscal_year', 'total', 'zakat_total', 'donation_count', 'lines_hash', 'snapshot', 'issued_at', 'issued_by', 'disk'])]
class TaxCertificate extends Model
{
    /** @use HasFactory<TaxCertificateFactory> */
    use HasFactory;

    public const FROZEN = ['certificate_no', 'donor_id', 'fiscal_year', 'total', 'zakat_total', 'donation_count', 'lines_hash', 'snapshot', 'issued_at'];

    protected $attributes = [
        'disk' => 'local',
        'zakat_total' => '0.00',
    ];

    protected static function booted(): void
    {
        static::updating(function (TaxCertificate $certificate): void {
            if ($certificate->isDirty(self::FROZEN)) {
                throw new DomainException('An issued tax certificate can never be changed. Reissue it instead.');
            }
        });

        static::deleting(function (): void {
            throw new DomainException('Tax certificates are never deleted. Void or reissue it instead.');
        });
    }

    public function fiscalYear(): FiscalYear
    {
        return new FiscalYear($this->fiscal_year);
    }

    public function isVoid(): bool
    {
        return $this->voided_at !== null;
    }

    public function isRendered(): bool
    {
        return $this->path !== null;
    }

    public function fileName(): string
    {
        return $this->certificate_no.'.pdf';
    }

    /**
     * @param  Builder<TaxCertificate>  $query
     */
    public function scopeValid(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    /**
     * @return BelongsTo<Donor, $this>
     */
    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'total' => 'decimal:2',
            'zakat_total' => 'decimal:2',
            'donation_count' => 'integer',
            'snapshot' => 'array',
            'issued_at' => 'datetime',
            'rendered_at' => 'datetime',
            'sent_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
