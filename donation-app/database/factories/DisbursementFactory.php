<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PayoutMethod;
use App\Models\Application;
use App\Models\Disbursement;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A cash disbursement request for an application, below every approval threshold.
 *
 * @extends Factory<Disbursement>
 */
class DisbursementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fund_id' => Fund::factory(),
            'application_id' => Application::factory(),
            'amount' => '5000.00',
            'method' => PayoutMethod::Cash,
            'payee_name' => fake()->name('female'),
            'purpose' => 'Monthly ration support.',
        ];
    }

    public function requestedBy(User $user): static
    {
        return $this->state(['requested_by' => $user->id]);
    }

    public function forProject(int $projectId): static
    {
        return $this->state(['application_id' => null, 'project_id' => $projectId]);
    }

    public function byWallet(string $number = '03001234567'): static
    {
        return $this->state(['method' => PayoutMethod::Wallet, 'payee_account' => $number]);
    }
}
