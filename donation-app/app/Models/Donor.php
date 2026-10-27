<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DonorType;
use App\Enums\SequenceKey;
use App\Models\Concerns\Auditable;
use App\Services\NumberSequenceService;
use App\Support\Cnic;
use Database\Factories\DonorFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * A person or company that gives (FR-DNR-01, FR-DNR-02).
 * Guard: donor (web session). Mobile API: Sanctum tokens.
 * A donor with a null password is a guest donor.
 *
 * @property int $id
 * @property string|null $donor_code
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $cnic
 * @property string|null $cnic_hash
 * @property string|null $ntn
 * @property DonorType $type
 * @property string|null $city
 * @property string|null $password
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $phone_verified_at
 * @property array<string, mixed>|null $preferences
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'email', 'phone', 'cnic', 'ntn', 'type', 'city', 'password', 'preferences'])]
#[Hidden(['password', 'remember_token', 'cnic', 'cnic_hash', 'ntn'])]
class Donor extends Authenticatable
{
    /** @use HasFactory<DonorFactory> */
    use Auditable, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Donor $donor): void {
            $donor->donor_code ??= app(NumberSequenceService::class)->next(SequenceKey::Donor);
        });

        static::saving(function (Donor $donor): void {
            if (blank($donor->email) && blank($donor->phone)) {
                throw new DomainException('A donor needs an email address or a phone number.');
            }

            if ($donor->isDirty('cnic')) {
                $cnic = $donor->cnic;
                $donor->cnic_hash = is_string($cnic) && $cnic !== '' ? Cnic::hash($cnic) : null;
            }
        });
    }

    /** "35202-•••••••-1" — for screens; the full CNIC is never displayed. */
    public function maskedCnic(): ?string
    {
        $cnic = $this->cnic;

        if ($cnic === null || $cnic === '') {
            return null;
        }

        $digits = Cnic::normalize($cnic);

        return strlen($digits) === 13
            ? substr($digits, 0, 5).'-•••••••-'.substr($digits, -1)
            : '•••••••••••••';
    }

    public function maskedNtn(): ?string
    {
        $ntn = $this->ntn;

        if ($ntn === null || $ntn === '') {
            return null;
        }

        return str_repeat('•', max(0, strlen($ntn) - 2)).substr($ntn, -2);
    }

    public function isGuest(): bool
    {
        return $this->password === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DonorType::class,
            'cnic' => 'encrypted',
            'ntn' => 'encrypted',
            'preferences' => 'array',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
