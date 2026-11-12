<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Donor;
use App\Support\ApiFormat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The logged-in donor ("/me"). CNIC and NTN only ever appear masked.
 *
 * @mixin Donor
 */
class DonorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Donor $donor */
        $donor = $this->resource;

        return [
            'id' => $donor->id,
            'donor_code' => $donor->donor_code,
            'name' => $donor->name,
            'type' => ApiFormat::enum($donor->type),
            'phone' => $donor->phone,
            'email' => $donor->email,
            'city' => $donor->city,
            'cnic_masked' => $donor->maskedCnic(),
            'has_cnic' => filled($donor->cnic),
            'phone_verified' => $donor->phone_verified_at !== null,
            'email_verified' => $donor->email_verified_at !== null,
            'preferences' => [
                'language' => $donor->prefersUrdu() ? 'ur' : 'en',
                'whatsapp_opt_in' => $donor->wantsWhatsApp(),
                'email_opt_in' => $donor->acceptsEmail(),
            ],
            'member_since' => ApiFormat::date($donor->created_at),
        ];
    }
}
