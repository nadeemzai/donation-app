<?php

declare(strict_types=1);

use App\Mail\DonorLoginCode;
use App\Models\Donor;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\Models\Activity;

// Week 7 Day 2 — app login: one-time code → Sanctum token; register; logout

beforeEach(fn () => Mail::fake());

function apiDonor(array $attributes = []): Donor
{
    return Donor::factory()->create(array_merge([
        'phone' => '0300'.fake()->unique()->numerify('#######'),
        'email' => 'donor'.fake()->unique()->numerify('#####').'@example.com',
        'password' => null,
    ], $attributes));
}

/** The code most recently emailed. */
function apiEmailedCode(): string
{
    $code = '';
    Mail::assertSent(DonorLoginCode::class, function (DonorLoginCode $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    return $code;
}

/** Log in through the API and return the plain token. */
function apiLogin(Donor $donor): string
{
    test()->postJson('/api/v1/auth/login', ['identifier' => $donor->email])->assertOk();

    return (string) test()->postJson('/api/v1/auth/otp/verify', [
        'identifier' => $donor->email,
        'code' => apiEmailedCode(),
        'device_name' => 'Test phone',
    ])->assertOk()->json('data.token');
}

// ── login ───────────────────────────────────────────────────────────────

it('sends a code and swaps it for a 90-day token', function () {
    $donor = apiDonor(['name' => 'Ayesha Khan']);

    $this->postJson('/api/v1/auth/login', ['identifier' => strtoupper((string) $donor->email)])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.expires_in', 600)
        ->assertJsonPath('data.sent_to', substr((string) $donor->email, 0, 1).'•••@example.com');

    $response = $this->postJson('/api/v1/auth/otp/verify', [
        'identifier' => $donor->email,
        'code' => apiEmailedCode(),
        'device_name' => 'Ayesha\'s Galaxy',
    ])->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.donor.name', 'Ayesha Khan')
        ->assertJsonPath('data.donor.email_verified', true);

    $token = PersonalAccessToken::findToken((string) $response->json('data.token'));
    expect($token)->not->toBeNull()
        ->and($token?->tokenable_id)->toBe($donor->id)
        ->and($token?->name)->toBe('Ayesha\'s Galaxy')
        ->and($token?->abilities)->toBe(['donor'])
        ->and($token?->expires_at?->isBetween(now()->addDays(89), now()->addDays(91)))->toBeTrue()
        ->and(data_get(Activity::query()->where('event', 'donor_login')->sole()->properties, 'where'))->toBe('app');
});

it('opens /api with the token it gave', function () {
    $token = apiLogin(apiDonor());
    app('auth')->forgetGuards();

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk()->assertJsonPath('message', 'Logged out.');
});

it('answers the same for a number that is not a donor, and sends nothing', function () {
    $this->postJson('/api/v1/auth/login', ['identifier' => '0300-9999999'])
        ->assertOk()
        ->assertJsonPath('data.sent_to', '0300•••9999');

    Mail::assertNothingSent();

    $this->postJson('/api/v1/auth/otp/verify', ['identifier' => '03009999999', 'code' => '123456', 'device_name' => 'x'])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['code'], 'errors');
});

it('validates the login fields', function (array $body, string $field) {
    $this->postJson('/api/v1/auth/otp/verify', $body)->assertStatus(422)->assertJsonValidationErrors([$field], 'errors');
})->with([
    'not a phone or email' => [['identifier' => '12345', 'code' => '123456', 'device_name' => 'x'], 'identifier'],
    'short code' => [['identifier' => 'a@example.com', 'code' => '123', 'device_name' => 'x'], 'code'],
    'no device name' => [['identifier' => 'a@example.com', 'code' => '123456'], 'device_name'],
]);

it('accepts a code typed with spaces', function () {
    $donor = apiDonor();
    $this->postJson('/api/v1/auth/login', ['identifier' => $donor->email]);
    $code = apiEmailedCode();

    $this->postJson('/api/v1/auth/otp/verify', [
        'identifier' => $donor->email,
        'code' => substr($code, 0, 3).' '.substr($code, 3),
        'device_name' => 'x',
    ])->assertOk();
});

it('kills a code after five wrong tries', function () {
    $donor = apiDonor();
    $this->postJson('/api/v1/auth/login', ['identifier' => $donor->email]);
    $code = apiEmailedCode();
    $wrong = $code === '111111' ? '222222' : '111111';

    // The IP limit is 5 a minute; move on a minute between bursts.
    foreach (range(1, 4) as $try) {
        $this->postJson('/api/v1/auth/otp/verify', ['identifier' => $donor->email, 'code' => $wrong, 'device_name' => 'x'])->assertStatus(422);
    }
    $this->travel(61)->seconds();
    $this->postJson('/api/v1/auth/otp/verify', ['identifier' => $donor->email, 'code' => $wrong, 'device_name' => 'x'])->assertStatus(422);

    $this->postJson('/api/v1/auth/otp/verify', ['identifier' => $donor->email, 'code' => $code, 'device_name' => 'x'])->assertStatus(422);
});

it('limits login requests per IP', function () {
    foreach (range(1, 5) as $try) {
        $this->postJson('/api/v1/auth/login', ['identifier' => "nobody{$try}@example.com"])->assertOk();
    }

    $this->postJson('/api/v1/auth/login', ['identifier' => 'nobody9@example.com'])
        ->assertStatus(429)
        ->assertJsonPath('success', false);
});

it('refuses more than three codes for the same number', function () {
    $donor = apiDonor();

    foreach (range(1, 3) as $try) {
        $this->postJson('/api/v1/auth/otp/send', ['identifier' => $donor->email])->assertOk();
    }

    $this->postJson('/api/v1/auth/otp/send', ['identifier' => $donor->email])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m): bool => str_starts_with($m, 'Too many codes asked for.'));
});

// ── register ────────────────────────────────────────────────────────────

it('registers a new donor and sends the code', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Bilal Ahmed',
        'phone' => '0321-7654321',
        'email' => 'Bilal@Example.com',
        'city' => 'Karachi',
        'language' => 'ur',
        'whatsapp_opt_in' => true,
    ])->assertOk()->assertJsonPath('data.sent_to', '0321•••4321');

    $donor = Donor::query()->where('phone', '03217654321')->sole();
    expect($donor)
        ->name->toBe('Bilal Ahmed')
        ->email->toBe('bilal@example.com')
        ->city->toBe('Karachi')
        ->password->toBeNull()
        ->and($donor->prefersUrdu())->toBeTrue()
        ->and($donor->wantsWhatsApp())->toBeTrue();

    // WhatsApp isn't set up in tests, so the code goes to the email.
    Mail::assertSent(DonorLoginCode::class, fn (DonorLoginCode $m): bool => $m->hasTo('bilal@example.com'));
});

it('never changes an existing donor through register', function () {
    $donor = apiDonor(['name' => 'Original Name', 'phone' => '03217654321', 'city' => 'Lahore']);

    $this->postJson('/api/v1/auth/register', ['name' => 'Someone Else', 'phone' => '0321 7654321', 'email' => 'attacker@example.com', 'city' => 'Quetta'])
        ->assertOk();

    expect(Donor::query()->count())->toBe(1)
        ->and($donor->fresh())
        ->name->toBe('Original Name')
        ->email->toBe($donor->email)
        ->city->toBe('Lahore');

    // The code goes to the real donor, never to the address in the request.
    Mail::assertNotSent(DonorLoginCode::class, fn (DonorLoginCode $m): bool => $m->hasTo('attacker@example.com'));
});

it('answers an archived donor\'s register like any other, and sends nothing', function () {
    apiDonor(['phone' => '03217654321'])->delete();

    $this->postJson('/api/v1/auth/register', ['name' => 'X Y', 'phone' => '03217654321'])->assertOk();

    Mail::assertNothingSent();
    expect(Donor::query()->count())->toBe(0);
});

it('needs a name and a Pakistani mobile to register', function () {
    $this->postJson('/api/v1/auth/register', ['phone' => '12345'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'phone'], 'errors');
});

// ── tokens & logout ─────────────────────────────────────────────────────

it('logs out this device only, or every device', function () {
    $donor = apiDonor();
    $phone = $donor->createToken('phone', ['donor'])->plainTextToken;
    $tablet = $donor->createToken('tablet', ['donor'])->plainTextToken;

    $this->withToken($phone)->postJson('/api/v1/auth/logout')->assertOk();
    expect($donor->tokens()->pluck('name')->all())->toBe(['tablet']);

    app('auth')->forgetGuards();
    $this->withToken($phone)->postJson('/api/v1/auth/logout')->assertUnauthorized();

    app('auth')->forgetGuards();
    $this->withToken($tablet)->postJson('/api/v1/auth/logout', ['all' => true])->assertOk();
    expect($donor->tokens()->count())->toBe(0);
});

it('stops accepting a token once it has expired', function () {
    $donor = apiDonor();
    $token = $donor->createToken('phone', ['donor'], now()->addDay())->plainTextToken;

    $this->travel(2)->days();

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertUnauthorized();
});

it('stops accepting the token of a donor who was archived', function () {
    $donor = apiDonor();
    $token = $donor->createToken('phone', ['donor'])->plainTextToken;
    $donor->delete();

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertUnauthorized();
});

it('does not let a website login cookie stand in for a token', function () {
    $this->actingAs(apiDonor(), 'donor')->postJson('/api/v1/auth/logout')->assertUnauthorized();
});
