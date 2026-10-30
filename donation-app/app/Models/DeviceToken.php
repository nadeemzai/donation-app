<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A mobile app push token (Week 7 Day 4). Used for notifications in a later week.
 *
 * @property int $id
 * @property int $donor_id
 * @property string $token
 * @property string $platform
 * @property string|null $app_version
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Donor $donor
 */
#[Fillable(['donor_id', 'token', 'platform', 'app_version', 'last_seen_at'])]
class DeviceToken extends Model
{
    public const PLATFORMS = ['android', 'ios'];

    /**
     * @return BelongsTo<Donor, $this>
     */
    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
