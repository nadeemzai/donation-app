<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Donation;
use App\Models\DonationRefund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Note: this only writes the refund row, for tests that need one to exist.
 * It does NOT debit the ledger or change the donation — use DonationService::refund().
 *
 * @extends Factory<DonationRefund>
 */
class DonationRefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'donation_id' => Donation::factory()->paid(),
            'fund_id' => fn (array $attributes): int => (int) Donation::query()->whereKey($attributes['donation_id'])->value('fund_id'),
            'amount' => '500.00',
            'method' => PaymentMethod::BankTransfer,
            'reason' => 'Donor asked for the money back',
            'refunded_at' => now(),
        ];
    }
}
