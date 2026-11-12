<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Fund;
use App\Support\ApiFormat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A fund donors can give to (Zakat, Sadqa…). The balance is never exposed.
 *
 * @mixin Fund
 */
class FundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Fund $fund */
        $fund = $this->resource;

        return [
            'id' => $fund->id,
            'code' => $fund->code,
            'name' => $fund->name,
            'type' => ApiFormat::enum($fund->type),
            'is_zakat' => $fund->is_zakat,
            'description' => $fund->description,
            'restriction' => $fund->restriction,
            'image_url' => $fund->imageUrl(),
            'web_url' => route('funds.show', strtolower($fund->code)),
        ];
    }
}
