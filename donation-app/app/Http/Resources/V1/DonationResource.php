<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Donation;
use App\Support\ApiFormat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of the logged-in donor's donations. Load "fund", "campaign" and "receipt".
 * Staff notes, proof files and who recorded it are never included.
 *
 * @mixin Donation
 */
class DonationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Donation $donation */
        $donation = $this->resource;
        $receipt = $donation->relationLoaded('receipt') ? $donation->receipt : null;

        return [
            'number' => $donation->donation_no,
            'status' => ApiFormat::enum($donation->status),
            'amount' => ApiFormat::money((string) $donation->amount, $donation->currency),
            'refunded' => ApiFormat::money((string) $donation->refunded_amount, $donation->currency),
            'net' => ApiFormat::money($donation->netAmount(), $donation->currency),
            'payment_method' => ApiFormat::enum($donation->payment_method),
            'channel' => ApiFormat::enum($donation->channel),
            'fund' => ['code' => $donation->fund->code, 'name' => $donation->fund->name, 'is_zakat' => $donation->fund->is_zakat],
            'campaign' => $donation->campaign !== null ? ['slug' => $donation->campaign->slug, 'title' => $donation->campaign->title] : null,
            'is_anonymous' => $donation->is_anonymous,
            'dedication' => $donation->dedication,
            'created_at' => ApiFormat::time($donation->created_at),
            'paid_at' => ApiFormat::time($donation->paid_at),
            'receipt' => $receipt !== null && ! $receipt->isVoid() ? [
                'id' => $receipt->id,
                'number' => $receipt->receipt_no,
                'download_url' => route('api.v1.me.receipts.download', $receipt->id), // needs the donor's token
            ] : null,
        ];
    }
}
