<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Http\Middleware\SetSiteLocale;
use App\Models\Donor;
use App\Models\Fund;
use App\Settings\GatewaySettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Urdu on the public website: an English / اردو switch, right-to-left layout, Nastaliq font,
// every website text translated (lang/ur.json). The admin panel stays English.

/**
 * Every text the website, the app API and donor emails pass through __() / trans_choice().
 *
 * @return list<string>
 */
function siteStrings(): array
{
    $files = array_merge(
        glob(resource_path('views/site/{,*/,*/*/}*.blade.php'), GLOB_BRACE) ?: [],
        glob(resource_path('views/layouts/*.blade.php')) ?: [],
        glob(resource_path('views/components/site/*.blade.php')) ?: [],
        glob(resource_path('views/payments/*.blade.php')) ?: [],
        glob(resource_path('views/mail/*.blade.php')) ?: [],
        glob(app_path('Http/{Controllers,Requests}/{Site,Api/V1}/*.php'), GLOB_BRACE) ?: [],
        glob(app_path('Services/{,Site/}*.php'), GLOB_BRACE) ?: [],
        glob(app_path('Support/{,Zakat/}*.php'), GLOB_BRACE) ?: [],
        glob(app_path('Mail/*.php')) ?: [],
    );

    $strings = [];

    foreach ($files as $file) {
        preg_match_all("/(?:__|trans_choice)\\(\\s*'((?:\\\\'|[^'])*)'/", (string) file_get_contents($file), $m);

        foreach ($m[1] as $s) {
            $strings[] = str_replace("\\'", "'", $s);
        }
    }

    return array_values(array_unique($strings));
}

/**
 * @return array<string, string>
 */
function urdu(): array
{
    /** @var array<string, string> $json */
    $json = json_decode((string) file_get_contents(lang_path('ur.json')), true, flags: JSON_THROW_ON_ERROR);

    return $json;
}

it('has an Urdu translation for every website text', function () {
    $missing = array_values(array_diff(siteStrings(), array_keys(urdu())));

    expect($missing)->toBe([]);
});

it('keeps every :placeholder in the Urdu text', function () {
    $broken = [];

    foreach (urdu() as $en => $ur) {
        preg_match_all('/:[a-z_]+/', $en, $a);
        preg_match_all('/:[a-z_]+/', $ur, $b);
        sort($a[0]);
        sort($b[0]);

        if ($a[0] !== $b[0]) {
            $broken[] = $en;
        }
    }

    expect($broken)->toBe([]);
});

it('shows English by default with a switch to Urdu', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<html lang="en" dir="ltr">', false)
        ->assertSee(route('locale.switch', 'ur'), false)
        ->assertSee('اردو');
});

it('switches to Urdu, right to left, and remembers it', function () {
    $this->from(route('campaigns.index'))
        ->get(route('locale.switch', 'ur'))
        ->assertRedirect(route('campaigns.index'))
        ->assertCookie(SetSiteLocale::COOKIE, 'ur');

    $this->get('/')
        ->assertOk()
        ->assertSee('<html lang="ur" dir="rtl">', false)
        ->assertSee('ابھی عطیہ دیں')
        ->assertSee('noto-nastaliq-urdu', false)
        ->assertSee(route('locale.switch', 'en'), false);

    $this->get(route('locale.switch', 'en'));
    $this->get('/')->assertSee('<html lang="en" dir="ltr">', false)->assertDontSee('noto-nastaliq-urdu', false);
});

it('remembers Urdu from the cookie on a new visit', function () {
    $this->withCookie(SetSiteLocale::COOKIE, 'ur')
        ->get('/zakat')
        ->assertOk()
        ->assertSee('میری زکوٰۃ کا حساب لگائیں');
});

it('refuses other languages and never redirects off the site', function () {
    $this->get('/lang/fr')->assertNotFound();

    $this->from('https://evil.example/phish')->get(route('locale.switch', 'ur'))->assertRedirect(route('home'));
});

it('shows Urdu validation messages', function () {
    $this->withSession(['locale' => 'ur'])
        ->from('/my-donations/login')
        ->post('/my-donations/login', ['identifier' => ''])
        ->assertSessionHasErrors();

    expect(session('errors')?->first())->not->toBe('')
        ->and((string) session('errors')?->first())->toMatch('/[\x{0600}-\x{06FF}]/u');
});

it('saves Urdu as the language of a new donor who gives in Urdu', function () {
    Storage::fake('local');
    $bank = app(GatewaySettings::class);
    $bank->bank_enabled = true;
    $bank->bank_name = 'Meezan Bank';
    $bank->bank_account_title = 'Imdaad Welfare Trust';
    $bank->bank_iban = 'PK36MEZN0000001123456702';
    $bank->save();

    $this->withSession(['locale' => 'ur'])->post('/donate', [
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '5000',
        'name' => 'عائشہ خان',
        'phone' => '03001234567',
        'payment_method' => 'bank_transfer',
        'reference' => 'TRF-1',
        'proof' => UploadedFile::fake()->image('t.jpg'),
        'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect();

    expect(Donor::query()->where('phone', '03001234567')->sole()->prefersUrdu())->toBeTrue();
});

it('keeps the admin panel in English', function () {
    $this->withCookie(SetSiteLocale::COOKIE, 'ur')
        ->actingAs(staffUser(Role::SuperAdmin))
        ->get('/admin')
        ->assertOk()
        ->assertSee('lang="en"', false) // Filament writes the <html> attributes on separate lines
        ->assertDontSee('lang="ur"', false)
        ->assertDontSee('noto-nastaliq-urdu', false);
});
