<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\User;
use Filament\Pages\SettingsPage;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Shared rules for every settings page (FR-ACC-03):
 *  - who can open it ($viewPermission) and who can change it ($editPermission)
 *  - secret fields are never sent to the browser; leaving one empty keeps the stored value
 */
abstract class BaseSettingsPage extends SettingsPage
{
    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static string $viewPermission = 'users.view_any';

    protected static string $editPermission = 'users.update';

    /**
     * Encrypted fields: blanked before the form is filled, dropped on save when left empty.
     *
     * @var list<string>
     */
    protected static array $secretFields = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can(static::$viewPermission);
    }

    public function canEdit(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can(static::$editPermission);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (static::$secretFields as $field) {
            $data[$field] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        foreach (static::$secretFields as $field) {
            if (blank($data[$field] ?? null)) {
                unset($data[$field]);
            }
        }

        return $data;
    }

    protected function secretHelperText(): string
    {
        return 'Stored encrypted. Leave empty to keep the current value.';
    }
}
