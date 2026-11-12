<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Donation;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A bare receipt row for tests that only need one to exist. Real receipts come from
 * ReceiptService::issue() (numbering, snapshot, PDF).
 *
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_no' => 'RC-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'donation_id' => Donation::factory()->paid(),
            'amount' => '1000.00',
            'snapshot' => ['donation' => ['no' => 'DN-TEST']],
            'issued_at' => now(),
        ];
    }
}
