<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Pages\Settings\ManageZakatSettings;
use App\Models\Fund;
use App\Settings\ZakatSettings;
use App\Support\Zakat\ZakatCalculator;
use Livewire\Livewire;

// Week 6 Day 3 — Zakat calculator

/** Gold Rs 300,000 and silver Rs 3,000 per tola → silver nisab Rs 157,500. */
function zakatRates(?int $gold = 300000, ?int $silver = 3000, string $basis = 'silver'): void
{
    $settings = app(ZakatSettings::class);
    $settings->gold_rate_per_tola = $gold;
    $settings->silver_rate_per_tola = $silver;
    $settings->nisab_basis = $basis;
    $settings->rates_date = '2026-11-18';
    $settings->save();
}

function zakatCalc(): ZakatCalculator
{
    return app(ZakatCalculator::class);
}

// ── the arithmetic ──────────────────────────────────────────────────────

it('works out 2.5% of wealth above the silver nisab', function () {
    zakatRates();

    $result = zakatCalc()->calculate(['gold_tola' => '2', 'cash' => '200000', 'bank' => '100000', 'debts' => '100000']);

    expect($result)
        ->goldValue->toBe('600000.00')
        ->totalAssets->toBe('900000.00')
        ->netWealth->toBe('800000.00')
        ->nisab->toBe('157500.00')
        ->nisabBasis->toBe('silver')
        ->meetsNisab->toBeTrue()
        ->zakat->toBe('20000.00')
        ->and($result->isDue())->toBeTrue()
        ->and($result->payableRupees())->toBe(20000);
});

it('says nothing is due below the nisab', function () {
    zakatRates();

    $result = zakatCalc()->calculate(['cash' => '100000']);

    expect($result->meetsNisab)->toBeFalse()->and($result->isDue())->toBeFalse();
});

it('uses the gold nisab for someone who owns only gold', function () {
    zakatRates();

    // 5 tola = Rs 1.5m: above the silver nisab, below 7.5 tola of gold (Rs 2.25m).
    $result = zakatCalc()->calculate(['gold_tola' => '5']);

    expect($result)->nisabBasis->toBe('gold')->nisab->toBe('2250000.00')->meetsNisab->toBeFalse();
});

it('uses the gold nisab everywhere when the admin chose gold', function () {
    zakatRates(basis: 'gold');

    expect(zakatCalc()->calculate(['cash' => '2000000'])->meetsNisab)->toBeFalse()
        ->and(zakatCalc()->calculate(['cash' => '2250000'])->meetsNisab)->toBeTrue();
});

it('never goes below zero when debts are bigger than assets', function () {
    zakatRates();

    $result = zakatCalc()->calculate(['cash' => '500000', 'debts' => '900000']);

    expect($result)->netWealth->toBe('0.00')->zakat->toBe('0.00')
        ->and($result->isDue())->toBeFalse();
});

it('rounds to the paisa and pre-fills whole rupees rounded up', function () {
    zakatRates(silver: 1); // nisab Rs 52.50

    $result = zakatCalc()->calculate(['cash' => '1001']);

    expect($result->zakat)->toBe('25.03')->and($result->payableRupees())->toBe(26);
});

it('warns instead of guessing when rates are not set', function () {
    zakatRates(gold: null, silver: null);

    $result = zakatCalc()->calculate(['gold_tola' => '3', 'cash' => '400000']);

    expect($result)
        ->goldValue->toBe('0.00')
        ->nisab->toBeNull()
        ->meetsNisab->toBeNull()
        ->zakat->toBe('10000.00')
        ->and($result->warnings)->toHaveCount(2);
});

// ── the page ────────────────────────────────────────────────────────────

it('shows today\'s rates and the nisab', function () {
    zakatRates();

    $this->get('/zakat')
        ->assertOk()
        ->assertSee('Rs 300,000')
        ->assertSee('Rs 157,500.00')
        ->assertSee('Rates as of 18 November 2026');
});

it('explains when rates have not been set', function () {
    $this->get('/zakat')->assertOk()->assertSee('rates have not been set yet');
});

it('calculates on the page and links to the Zakat fund with the amount', function () {
    zakatRates();
    $fund = Fund::factory()->zakat()->create();

    $this->post('/zakat', ['gold_tola' => '2', 'cash' => '2,00,000', 'bank' => '100 000', 'debts' => '100000'])
        ->assertOk()
        ->assertSee('Rs 20,000.00')
        ->assertSee('Pay this Zakat')
        ->assertSee(route('donate', ['fund' => strtolower($fund->code), 'amount' => 20000]));
});

it('says when no Zakat is due', function () {
    zakatRates();

    $this->post('/zakat', ['cash' => '50000'])
        ->assertOk()
        ->assertSee('No Zakat is due')
        ->assertDontSee('Pay this Zakat');
});

it('rejects figures that are not numbers', function (string $field, string $value) {
    $this->from('/zakat')->post('/zakat', [$field => $value])->assertSessionHasErrors($field);
})->with([
    'letters' => ['cash', 'lots'],
    'negative' => ['debts', '-500'],
    'gold in grams with a unit' => ['gold_tola', '20g'],
]);

it('appears in the main menu', function () {
    $this->get('/')->assertOk()->assertSee(route('zakat'), false);
});

it('pre-fills the donate form with the amount from the calculator', function () {
    $fund = Fund::factory()->zakat()->create();

    $this->get('/donate?fund='.strtolower($fund->code).'&amount=20000')
        ->assertOk()
        ->assertViewHas('prefillAmount', '20000')
        ->assertViewHas('selectedFund', (string) $fund->id);

    $this->get('/donate?amount=abc')->assertViewHas('prefillAmount', '');
});

// ── admin ───────────────────────────────────────────────────────────────

it('lets website staff open the rates page, nobody else', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/settings/zakat')->assertStatus($status);
})->with([
    'super admin' => [Role::SuperAdmin, 200],
    'manager' => [Role::Manager, 200],
    'campaign manager' => [Role::CampaignManager, 200],
    'finance' => [Role::Finance, 403],
    'auditor' => [Role::Auditor, 403],
]);

it('saves new rates with their date', function () {
    $this->actingAs(staffUser(Role::CampaignManager));

    Livewire::test(ManageZakatSettings::class)
        ->fillForm(['gold_rate_per_tola' => 310000, 'silver_rate_per_tola' => 3200, 'rates_date' => now()->toDateString(), 'nisab_basis' => 'silver'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(ZakatSettings::class))
        ->gold_rate_per_tola->toBe(310000)
        ->silver_rate_per_tola->toBe(3200)
        ->rates_date->toBe(now()->toDateString());
});

it('needs the date when rates are entered', function () {
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(ManageZakatSettings::class)
        ->fillForm(['gold_rate_per_tola' => 310000, 'rates_date' => null, 'nisab_basis' => 'silver'])
        ->call('save')
        ->assertHasFormErrors(['rates_date']);
});
