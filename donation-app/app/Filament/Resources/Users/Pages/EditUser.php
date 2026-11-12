<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Role;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role as RoleModel;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [UserResource::resetMfaAction()];
    }

    /**
     * Server-side guard: the last active Super Admin can be neither deactivated nor given
     * another role, so nobody is ever locked out of users and settings.
     *
     * Runs in beforeSave: Filament saves the role (a relationship) while it reads the form,
     * before mutateFormDataBeforeSave(), so the check has to happen here, on the raw form data.
     */
    protected function beforeSave(): void
    {
        $record = $this->getRecord();
        assert($record instanceof User);

        $data = is_array($this->data) ? $this->data : [];

        if ($record->getKey() === Auth::id()) {
            $data['is_active'] = true; // you cannot deactivate yourself (see below)
        }

        if ($record->hasRole(Role::SuperAdmin->value) && $record->is_active && $this->wouldStopBeingActiveSuperAdmin($data) && $this->otherActiveSuperAdmins($record) === 0) {
            Notification::make()->danger()
                ->title('This is the only active Super Admin')
                ->body('Make another staff user Super Admin first, then change this account.')
                ->persistent()
                ->send();

            $this->halt(shouldRollbackDatabaseTransaction: true);
        }
    }

    /**
     * Server-side guard: nobody can deactivate their own account, even if the disabled
     * toggle is bypassed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->getRecord()->getKey() === Auth::id()) {
            $data['is_active'] = true;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function wouldStopBeingActiveSuperAdmin(array $data): bool
    {
        if (array_key_exists('is_active', $data) && ! (bool) $data['is_active']) {
            return true;
        }

        $chosen = $data['roles'] ?? null;
        $ids = array_map('intval', array_values(is_array($chosen) ? $chosen : (is_scalar($chosen) ? [$chosen] : [])));
        $superAdminId = RoleModel::query()->where('name', Role::SuperAdmin->value)->value('id');

        return $ids !== [] && ! in_array((int) $superAdminId, $ids, true);
    }

    private function otherActiveSuperAdmins(User $record): int
    {
        return User::query()
            ->whereKeyNot($record->getKey())
            ->where('is_active', true)
            ->role(Role::SuperAdmin->value)
            ->count();
    }
}
