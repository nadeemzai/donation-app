<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\QurbaniBooking;
use App\Support\ApiFormat;
use App\Support\QurbaniToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Qurbani booking. `booking_token` is what POST /donations needs to pay it.
 *
 * @mixin QurbaniBooking
 */
class QurbaniBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var QurbaniBooking $booking */
        $booking = $this->resource;

        return [
            'booking_no' => $booking->booking_no,
            'status' => ApiFormat::enum($booking->status),
            'animal' => ApiFormat::enum($booking->offering->animal),
            'shares' => $booking->shares,
            'names' => $booking->names(),
            'price_per_share' => ApiFormat::money((string) $booking->price_per_share),
            'total' => ApiFormat::money((string) $booking->total_amount),
            'hold_until' => ApiFormat::time($booking->hold_until),
            'booking_token' => QurbaniToken::for($booking),
        ];
    }
}
