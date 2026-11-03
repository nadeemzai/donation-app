<?php

declare(strict_types=1);

use App\Enums\DonorType;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Filament\Resources\Donors\Pages\CreateDonor;
use App\Mail\DonorLoginCode;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Payments\GatewayRegistry;
use App\Settings\GatewaySettings;
use App\Support\Phone;
use App\Support\WhatsAppNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\FakeGateway;

// Overseas donors: mobile numbers abroad (+44…) or email only; bank transfer from abroad

beforeEach(function () {
    Storage::fake('local');
    Mail::fake();

    $bank = app(GatewaySettings::class);
    $bank->bank_enabled = true;
    $bank->bank_name = 'Meezan Bank';
    $bank->bank_iban = 'PK36MEZN0000001123456702';
    $bank->bank_swift = 'MEZNPKKA';
    $bank->save();

    app(GatewayRegistry::class)->register(new FakeGateway(PaymentMethod::JazzCash));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function overseasForm(array $overrides = []): array
{
    return array_merge([
        'fund_id' => $overrides['fund_id'] ?? Fund::factory()->create()->id,
        'amount' => '25000',
        'name' => 'Usman Tariq',
        'phone' => '+44 7700 900123',
        'email' => 'usman@example.co.uk',
        'payment_method' => PaymentMethod::BankTransfer->value,
        'reference' => 'WISE-55120931',
        'proof' => UploadedFile::fake()->image('transfer.jpg'),
        'idempotency_key' => (string) Str::uuid(),
    ], $overrides);
}

// ── numbers ─────────────────────────────────────────────────────────────

it('understands numbers in Pakistan and abroad', function (string $input, ?string $expected) {
    expect(Phone::normalize($input))->toBe($expected);
})->with([
    'Pakistani local' => ['0300-1234567', '03001234567'],
    'Pakistani international' => ['+92 300 1234567', '03001234567'],
    'UK with +' => ['+44 7700 900123', '+447700900123'],
    'UK with 00' => ['0044 7700 900123', '+447700900123'],
    'UAE' => ['+971 50 123 4567', '+971501234567'],
    'USA with brackets' => ['+1 (202) 555-0123', '+12025550123'],
    'abroad without a country code' => ['7700 900123', null],
    'Pakistani landline' => ['+92 42 35761234', null],
    'far too short' => ['+12', null],
    'empty' => ['', null],
]);

it('knows which numbers can use JazzCash and Easypaisa', function () {
    expect(Phone::isPakistaniMobile('0300-1234567'))->toBeTrue()
        ->and(Phone::isPakistaniMobile('+44 7700 900123'))->toBeFalse()
        ->and(Phone::isOverseas('+971 50 123 4567'))->toBeTrue()
        ->and(Phone::isOverseas('0300-1234567'))->toBeFalse();
});

it('sends WhatsApp to numbers abroad too', function () {
    expect(WhatsAppNumber::chatId('+44 7700 900123'))->toBe('447700900123@c.us')
        ->and(WhatsAppNumber::chatId('+971501234567'))->toBe('971501234567@c.us')
        ->and(WhatsAppNumber::chatId('0300-1234567'))->toBe('923001234567@c.us');
});

// ── website ─────────────────────────────────────────────────────────────

it('takes a bank transfer from a donor abroad', function () {
    $this->post('/donate', overseasForm())->assertRedirectContains('/donate/thank-you/');

    $donation = Donation::sole();
    expect($donation->donor)->phone->toBe('+447700900123')->email->toBe('usman@example.co.uk')
        ->and($donation->payment_method)->toBe(PaymentMethod::BankTransfer)
        ->and($donation->reference)->toBe('WISE-55120931');
});

it('takes a donation with only an email', function () {
    $this->post('/donate', overseasForm(['phone' => '']))->assertRedirectContains('/donate/thank-you/');

    expect(Donation::sole()->donor)->phone->toBeNull()->email->toBe('usman@example.co.uk');
});

it('needs a phone or an email', function () {
    $this->from('/donate')->post('/donate', overseasForm(['phone' => '', 'email' => '']))
        ->assertSessionHasErrors(['phone', 'email']);

    expect(Donation::query()->count())->toBe(0);
});

it('sends donors abroad to bank transfer instead of JazzCash', function () {
    $this->from('/donate')->post('/donate', overseasForm(['payment_method' => PaymentMethod::JazzCash->value]))
        ->assertSessionHasErrors(['payment_method' => 'JazzCash needs a Pakistani mobile number. From abroad, please pay by bank transfer.']);

    expect(Donation::query()->count())->toBe(0);
});

it('shows donors abroad how to pay, with the SWIFT code', function () {
    $this->get('/donate')
        ->assertOk()
        ->assertSee('Donating from abroad?')
        ->assertSee('MEZNPKKA')
        ->assertSee('+44 7700 900123');
});

it('matches a donor abroad by their number the next time', function () {
    $donor = Donor::factory()->create(['phone' => '+447700900123']);

    $this->post('/donate', overseasForm(['phone' => '0044 7700 900123', 'email' => '']));

    expect(Donation::sole()->donor_id)->toBe($donor->id);
});

// ── portal, app and admin ───────────────────────────────────────────────

it('logs a donor abroad into the portal by their number', function () {
    $donor = Donor::factory()->create(['phone' => '+447700900123', 'email' => 'usman@example.co.uk']);

    $this->post('/my-donations/login', ['identifier' => '+44 7700 900123'])->assertRedirect('/my-donations/verify');

    // WhatsApp isn't set up in tests, so the code goes to the email.
    Mail::assertSent(DonorLoginCode::class, fn (DonorLoginCode $m): bool => $m->hasTo((string) $donor->email));
});

it('registers in the app with only an email', function () {
    $this->postJson('/api/v1/auth/register', ['name' => 'Sara Malik', 'email' => 'sara@example.ae'])
        ->assertOk()
        ->assertJsonPath('data.sent_to', 's•••@example.ae');

    expect(Donor::query()->where('email', 'sara@example.ae')->sole()->phone)->toBeNull();
    Mail::assertSent(DonorLoginCode::class, fn (DonorLoginCode $m): bool => $m->hasTo('sara@example.ae'));
});

it('registers in the app with a number abroad', function () {
    $this->postJson('/api/v1/auth/register', ['name' => 'Sara Malik', 'phone' => '+971 50 123 4567', 'email' => 'sara@example.ae'])
        ->assertOk()
        ->assertJsonPath('data.sent_to', '+971•••4567');

    expect(Donor::query()->where('phone', '+971501234567')->exists())->toBeTrue();
});

it('lets a donor abroad give by bank transfer in the app', function () {
    $this->postJson('/api/v1/donations', [
        'fund_code' => Fund::factory()->create()->code,
        'amount' => '25000',
        'payment_method' => 'bank_transfer',
        'name' => 'Usman Tariq',
        'phone' => '+44 7700 900123',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated()
        ->assertJsonPath('data.next_step', 'upload_proof')
        ->assertJsonPath('data.bank.swift', 'MEZNPKKA');

    $this->getJson('/api/v1/app/config')
        ->assertJsonPath('data.donate.methods.0.needs_pakistani_mobile', true)
        ->assertJsonPath('data.donate.bank.swift', 'MEZNPKKA');
});

it('lets staff save a donor with a number abroad', function () {
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(CreateDonor::class)
        ->fillForm(['type' => DonorType::Individual->value, 'name' => 'Usman Tariq', 'phone' => '+971 50 123 4567'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Donor::query()->where('phone', '+971501234567')->exists())->toBeTrue();
});
