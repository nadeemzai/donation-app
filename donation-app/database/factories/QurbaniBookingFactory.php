<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DonationChannel;
use App\Models\Donor;
use App\Models\QurbaniBooking;
use App\Models\QurbaniOffering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending one-share booking, held for an hour. Tests that need names use QurbaniService::book().
 *
 * @extends Factory<QurbaniBooking>
 */
class QurbaniBookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offering_id' => QurbaniOffering::factory(),
            'donor_id' => Donor::factory(),
            'shares' => 1,
            'channel' => DonationChannel::Web,
            'hold_until' => now()->addHour(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (QurbaniBooking $booking): void {
            $offering = QurbaniOffering::query()->findOrFail($booking->offering_id);
            if ($booking->getAttribute('season_id') === null) {
                $booking->season_id = $offering->season_id;
            }

            if ($booking->getAttribute('price_per_share') === null) {
                $booking->price_per_share = (string) $offering->price_per_share;
            }
        });
    }
}
