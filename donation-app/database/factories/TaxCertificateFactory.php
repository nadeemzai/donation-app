<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Donor;
use App\Models\TaxCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A bare certificate row. Real certificates come from TaxCertificateService::generate().
 *
 * @extends Factory<TaxCertificate>
 */
class TaxCertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'certificate_no' => 'TC-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'donor_id' => Donor::factory(),
            'fiscal_year' => 2025,
            'total' => '10000.00',
            'zakat_total' => '0.00',
            'donation_count' => 1,
            'lines_hash' => hash('sha256', 'test'),
            'snapshot' => ['lines' => []],
            'issued_at' => now(),
        ];
    }
}
