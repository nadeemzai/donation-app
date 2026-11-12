<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Services\MfaService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role as RoleModel;

// W12-D1 — resetting a lost authenticator, and never losing the last Super Admin.

describe('resetting two-factor', function () {
    it('removes the authenticator, signs the person out and logs why', function () {
        config()->set('session.driver', 'database');
        $finance = staffUser(Role::Finance);
        $founder = staffUser(Role::SuperAdmin);
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $finance->id, 'payload' => '', 'last_activity' => time()]);

        app(MfaService::class)->reset($finance, 'Lost phone', $founder);

        expect($finance->fresh()->hasMfaEnabled())->toBeFalse()
            ->and(DB::table('sessions')->where('user_id', $finance->id)->exists())->toBeFalse()
            ->and(Activity::query()->where('event', 'mfa_reset')->sole()->properties['reason'])->toBe('Lost phone');

        // Next visit: straight to set up a new authenticator.
        $this->actingAs($finance->fresh())->get('/admin')->assertRedirectContains('/admin/profile');
    });

    it('needs a reason, an authenticator to reset, and someone other than yourself', function () {
        $founder = staffUser(Role::SuperAdmin);

        expect(fn () => app(MfaService::class)->reset(staffUser(Role::Finance), ' ', $founder))->toThrow(DomainException::class, 'why')
            ->and(fn () => app(MfaService::class)->reset(staffUser(Role::Manager), 'x', $founder))->toThrow(DomainException::class, 'nothing to reset')
            ->and(fn () => app(MfaService::class)->reset($founder, 'x', $founder))->toThrow(DomainException::class, 'your own');
    });

    it('is a button for Super Admins on the staff list', function () {
        $finance = staffUser(Role::Finance);
        staffUser(Role::Trustee, withMfa: false);

        Livewire::actingAs(staffUser(Role::SuperAdmin))->test(ListUsers::class)
            ->assertSee('Needed — not set up', false)
            ->callAction(TestAction::make('resetMfa')->table($finance), ['reason' => 'Lost phone, confirmed by call'])
            ->assertHasNoActionErrors();

        expect($finance->fresh()->hasMfaEnabled())->toBeFalse();
    });

    it('is hidden from managers, who can only view staff', function () {
        $finance = staffUser(Role::Finance);

        Livewire::actingAs(staffUser(Role::Manager))->test(ListUsers::class)
            ->assertActionHidden(TestAction::make('resetMfa')->table($finance));
    });

    it('works from the console when every Super Admin is locked out', function () {
        $founder = staffUser(Role::SuperAdmin, ['email' => 'founder@example.org']);

        $this->artisan('mfa:reset', ['email' => 'founder@example.org', '--reason' => 'Lost phone', '--force' => true])
            ->assertSuccessful();

        expect($founder->fresh()->hasMfaEnabled())->toBeFalse();

        $this->artisan('mfa:reset', ['email' => 'nobody@example.org', '--reason' => 'x', '--force' => true])->assertFailed();
    });
});

describe('the last Super Admin', function () {
    it('cannot be deactivated or given another role', function () {
        $founder = staffUser(Role::SuperAdmin); // the only Super Admin
        $managerRole = RoleModel::findByName(Role::Manager->value, 'web');

        Livewire::actingAs($founder)->test(EditUser::class, ['record' => $founder->getRouteKey()])
            ->fillForm(['roles' => [$managerRole->id]])
            ->call('save')
            ->assertNotified('This is the only active Super Admin');

        expect($founder->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
    });

    it('can be changed once another Super Admin is active', function () {
        $founder = staffUser(Role::SuperAdmin);
        $deputy = staffUser(Role::SuperAdmin);

        Livewire::actingAs($founder)->test(EditUser::class, ['record' => $deputy->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($deputy->fresh()->is_active)->toBeFalse();
    });
});
