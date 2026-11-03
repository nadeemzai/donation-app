<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/** Dashboard widgets follow the permission matrix like every screen. */
trait ChecksPermission
{
    protected static function userCan(string ...$permissions): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
