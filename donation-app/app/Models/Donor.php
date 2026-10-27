<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DonorType;
use App\Support\Cnic;
use Database\Factories\DonorFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A person or company that gives (FR-DNR-01, FR-DNR-02).
 * Guard: donor (web session). Mobile API: Sanctum tokens.
 * A donor with a null password is a guest donor.
 */
#[Fillable(['name', 'email', 'phone', 'cnic', 'ntn', 'type', 'city', 'password', 'preferences'])]
#[Hidden(['password', 'remember_token', 'cnic', 'cnic_hash', 'ntn'])]
class Donor extends Authenticatable
{
    /** @use HasFactory<DonorFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected static function booted(): void
    {
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
