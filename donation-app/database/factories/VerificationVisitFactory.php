<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VisitRecommendation;
use App\Models\Application;
use App\Models\VerificationVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A visit made today that recommends approval.
 *
 * @extends Factory<VerificationVisit>
 */
class VerificationVisitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'visit_date' => today()->toDateString(),
            'findings' => 'Two rooms, no earning member, children in school.',
            'household_size_seen' => 5,
            'monthly_income_found' => '6000.00',
            'recommendation' => VisitRecommendation::Approve,
            'zakat_eligible' => true,
            'zakat_reason' => 'Below nisab, no savings or gold.',
        ];
    }
}
