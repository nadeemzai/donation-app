<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\User;
use Filament\Pages\SettingsPage;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelSettings\Settings;
use UnitEnum;

/**
 * Shared rules for every settings page (FR-ACC-03):
 *  - who can open it ($viewPermission) and who can change it ($editPermission)
 *  - secret fields are never sent to the browser; leaving one empty keeps the stored value
 *  - every save is written to the audit trail (log "settings"), secrets masked
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

    /**
     * Settings values captured just before saving, for the audit trail.
     *
     * @var array<string, mixed>
     */
    protected array $settingsBeforeSave = [];

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

    protected function beforeSave(): void
    {
        $this->settingsBeforeSave = $this->currentSettings()->toArray();
    }

    /**
     * FR-AUD-01: record who changed which setting, before → after.
     * Secret values are never written to the log — only that they changed.
     */
    protected function afterSave(): void
    {
        $settings = $this->currentSettings();
        $old = [];
        $new = [];

        foreach ($settings->toArray() as $field => $value) {
            $before = $this->settingsBeforeSave[$field] ?? null;

            if ($before === $value) {
                continue;
            }

            $isSecret = in_array($field, static::$secretFields, true);
            $old[$field] = $isSecret ? '[hidden]' : $before;
            $new[$field] = $isSecret ? '[changed]' : $value;
        }

        if ($new === []) {
            return;
        }

        $user = Auth::user();

        activity('settings')
            ->causedBy($user instanceof User ? $user : null)
            ->event('updated')
            ->withChanges(['attributes' => $new, 'old' => $old])
            ->withProperty('group', $settings::group())
            ->log('Updated '.static::getNavigationLabel().' settings');
    }

    private function currentSettings(): Settings
    {
        /** @var Settings $settings */
        $settings = app(static::getSettings());

        return $settings;
    }

    protected function secretHelperText(): string
    {
        return 'Stored encrypted. Leave empty to keep the current value.';
    }
}
