<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\QurbaniBooking;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Carries a Qurbani booking from the booking form through the donate form and the app's
 * donation call (Week 11). Encrypted, so nobody can pay into — or see — someone else's booking.
 */
final class QurbaniToken
{
    public static function for(QurbaniBooking $booking): string
    {
        return Crypt::encryptString('qurbani-booking:'.$booking->id);
    }

    /** The booking behind a token (any status — the caller decides if it can be paid). */
    public static function booking(?string $token): ?QurbaniBooking
    {
        if ($token === null || $token === '') {
            return null;
        }

        try {
            $value = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        if (preg_match('/^qurbani-booking:(\d+)$/', $value, $m) !== 1) {
            return null;
        }

        return QurbaniBooking::query()->with(['donor', 'season.fund', 'offering', 'shareRows'])->find((int) $m[1]);
    }
}
