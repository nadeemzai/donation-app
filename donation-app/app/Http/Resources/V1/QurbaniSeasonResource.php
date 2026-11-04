<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\QurbaniOffering;
use App\Models\QurbaniSeason;
use App\Services\QurbaniService;
use App\Support\ApiFormat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The Qurbani season open for booking, with what is offered and how many shares are left.
 *
 * @mixin QurbaniSeason
 */
class QurbaniSeasonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var QurbaniSeason $season */
        $season = $this->resource;

        return [
            'id' => $season->id,
            'name' => $season->name,
            'eid_date' => ApiFormat::date($season->eid_date),
            'slaughter_days' => array_values(array_map(fn ($d): ?string => ApiFormat::date($d), $season->slaughterDays())),
            'booking_closes_on' => ApiFormat::date($season->booking_closes_on),
            'is_taking_bookings' => $season->isTakingBookings(),
            'allow_overseas' => $season->allow_overseas,
            'max_shares_per_booking' => QurbaniService::MAX_SHARES,
            'offerings' => $season->offerings->where('is_active', true)->values()->map(fn (QurbaniOffering $o): array => [
                'id' => $o->id,
                'animal' => ApiFormat::enum($o->animal),
                'shares_per_animal' => $o->shares_per_animal,
                'price_per_share' => ApiFormat::money((string) $o->price_per_share),
                'shares_left' => $o->sharesLeft(),
            ])->all(),
            'web_url' => route('qurbani'),
        ];
    }
}
