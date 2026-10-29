<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Pages\Settings\ManageApprovalSettings;
use App\Filament\Pages\Settings\ManageGatewaySettings;
use App\Filament\Pages\Settings\ManageGeneralSettings;
use App\Filament\Pages\Settings\ManageNotificationSettings;
use App\Settings\ApprovalSettings;
use App\Settings\GatewaySettings;
use App\Settings\GeneralSettings;
use App\Settings\NotificationSettings;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

// FR-ACC-03, FR-PAY-04, BR-06 — who can see and change settings

it('lets the right roles open each settings page', function (string $url, Role $role, int $status) {
    $this->actingAs(staffUser($role))->get($url)->assertStatus($status);
})->with([
    'general · super admin' => ['/admin/settings/general', Role::SuperAdmin, 200],
    'general · manager (view)' => ['/admin/settings/general', Role::Manager, 200],
    'general · auditor (view)' => ['/admin/settings/general', Role::Auditor, 200],
    'general · finance' => ['/admin/settings/general', Role::Finance, 403],
    'numbering · manager (view)' => ['/admin/settings/numbering', Role::Manager, 200],
    'approvals · manager (view)' => ['/admin/settings/approvals', Role::Manager, 200],
    'gateways · super admin' => ['/admin/settings/gateways', Role::SuperAdmin, 200],
    'gateways · manager' => ['/admin/settings/gateways', Role::Manager, 403],
    'gateways · finance' => ['/admin/settings/gateways', Role::Finance, 403],
    'notifications · super admin' => ['/admin/settings/notifications', Role::SuperAdmin, 200],
    'notifications · manager' => ['/admin/settings/notifications', Role::Manager, 403],
]);

it('lets super admin update organisation settings', function () {
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(ManageGeneralSettings::class)
        ->fillForm(['org_name' => 'Roshni Foundation', 'registration_no' => 'REG-123'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(GeneralSettings::class)->org_name)->toBe('Roshni Foundation');
});

it('does not let a manager change settings they can view', function () {
    $this->actingAs(staffUser(Role::Manager));

    Livewire::test(ManageGeneralSettings::class)
        ->fillForm(['org_name' => 'Changed by manager'])
        ->call('save');

    expect(app(GeneralSettings::class)->org_name)->toBe('Imdaad');
});

it('lets super admin switch founder self-approval off', function () {
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(ManageApprovalSettings::class)
        ->fillForm(['allow_founder_self_approval' => false, 'second_level_threshold' => 50000, 'trustee_threshold' => 250000])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(ApprovalSettings::class))
        ->allow_founder_self_approval->toBeFalse()
        ->second_level_threshold->toBe(50000);
});

it('rejects a trustee amount below the second-approver amount', function () {
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(ManageApprovalSettings::class)
        ->fillForm(['second_level_threshold' => 100000, 'trustee_threshold' => 5000])
        ->call('save')
        ->assertHasFormErrors(['trustee_threshold']);
});

it('never sends stored secrets to the browser', function () {
    $settings = app(GatewaySettings::class);
    $settings->jazzcash_password = 'jc-secret-pass';
    $settings->save();

    $this->actingAs(staffUser(Role::SuperAdmin));

    $page = Livewire::test(ManageGatewaySettings::class);

    expect($page->get('data.jazzcash_password'))->toBeNull()
        ->and($page->html())->not->toContain('jc-secret-pass');
});

it('keeps a stored secret when the field is left empty, and replaces it when filled', function () {
    $settings = app(GatewaySettings::class);
    $settings->jazzcash_password = 'original-secret';
    $settings->save();

    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(ManageGatewaySettings::class)
        ->fillForm(['jazzcash_merchant_id' => 'MC12345'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(GatewaySettings::class))
        ->jazzcash_merchant_id->toBe('MC12345')
        ->jazzcash_password->toBe('original-secret');

    Livewire::test(ManageGatewaySettings::class)
        ->fillForm(['jazzcash_password' => 'new-secret'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(GatewaySettings::class)->jazzcash_password)->toBe('new-secret');
});

it('validates and normalises the IBAN', function () {
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(ManageGatewaySettings::class)
        ->fillForm(['bank_iban' => 'not-an-iban'])
        ->call('save')
        ->assertHasFormErrors(['bank_iban']);

    Livewire::test(ManageGatewaySettings::class)
        ->fillForm(['bank_iban' => 'PK36SCBL0000001123456702'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(GatewaySettings::class)->bank_iban)->toBe('PK36SCBL0000001123456702');
});

it('reports a connected Green API instance', function () {
    $settings = app(NotificationSettings::class);
    $settings->green_api_id_instance = '1101000001';
    $settings->green_api_token_instance = 'token-abc';
    $settings->save();

    Http::fake(['*/waInstance1101000001/getStateInstance/token-abc' => Http::response(['stateInstance' => 'authorized'])]);

    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(ManageNotificationSettings::class)
        ->call('checkWhatsApp')
        ->assertNotified('WhatsApp connected');
});

it('explains when Green API is not configured', function () {
    Http::fake();
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(ManageNotificationSettings::class)
        ->call('checkWhatsApp')
        ->assertNotified('WhatsApp not connected');

    Http::assertNothingSent();
});
