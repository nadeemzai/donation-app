<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\MessageStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Filament\Resources\TaxCertificates\Pages\ListTaxCertificates;
use App\Jobs\GenerateTaxCertificate;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\MessageLog;
use App\Models\TaxCertificate;
use App\Models\User;
use App\Services\DonationService;
use App\Services\TaxCertificateService;
use App\Support\FiscalYear;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\LaravelPdf\Facades\Pdf;

// FR-RCP-03, BR-12 — annual tax certificates (FY = 1 July – 30 June)

function certificates(): TaxCertificateService
{
    return app(TaxCertificateService::class);
}

function taxDonor(bool $withCnic = true): Donor
{
    return Donor::factory()->create([
        'name' => 'Sana Malik',
        'email' => 'sana'.fake()->unique()->numerify('#####').'@example.org',
        'phone' => '0300'.fake()->unique()->numerify('#######'),
        'cnic' => $withCnic ? '35202-'.fake()->unique()->numerify('#######').'-4' : null,
        'ntn' => null,
    ]);
}

/** A donation confirmed at a given moment (Pakistan time). */
function paidOn(Donor $donor, string $at, string $amount, ?Fund $fund = null): Donation
{
    $service = app(DonationService::class);
    $by = User::factory()->create();

    $donation = $service->create([
        'donor_id' => $donor->id,
        'fund_id' => ($fund ?? Fund::factory()->create(['name' => 'General Fund']))->id,
        'amount' => $amount,
        'channel' => DonationChannel::Admin,
        'payment_method' => PaymentMethod::Cash,
    ], $by);

    return $service->confirm($donation, $by, null, CarbonImmutable::parse($at, 'Asia/Karachi'));
}

it('certifies a year of donations: in-year only, after refunds, Zakat shown separately', function () {
    $donor = taxDonor();
    $zakat = Fund::factory()->zakat()->create();
    paidOn($donor, '2025-06-30 23:00', '999.00');        // previous year
    paidOn($donor, '2025-07-01 09:00', '10000.00');      // first day
    paidOn($donor, '2025-12-15 12:00', '5000.00', $zakat);
    $partly = paidOn($donor, '2026-03-01 10:00', '4000.00');
    $full = paidOn($donor, '2026-04-01 10:00', '2500.00');
    paidOn($donor, '2026-07-01 00:30', '777.00');        // next year
    app(DonationService::class)->refund($partly->fresh(), '1000.00', 'Partial', PaymentMethod::Cash);
    app(DonationService::class)->refund($full->fresh(), '2500.00', 'All back', PaymentMethod::Cash);
    Pdf::fake();

    $certificate = certificates()->generate($donor, new FiscalYear(2025));

    expect($certificate->certificate_no)->toBe('TC-'.now()->year.'-000001')
        ->and($certificate->fiscal_year)->toBe(2025)
        ->and($certificate->total)->toBe('18000.00')       // 10,000 + 5,000 + (4,000 − 1,000)
        ->and($certificate->zakat_total)->toBe('5000.00')
        ->and($certificate->donation_count)->toBe(3)
        ->and($certificate->isRendered())->toBeTrue()
        ->and($certificate->path)->toBe("tax-certificates/2025/{$certificate->certificate_no}.pdf");

    Pdf::assertViewIs('pdf.tax-certificate');
    Pdf::assertSee(['DONATION TAX CERTIFICATE', 'Fiscal year 2025-26', 'Sana Malik', 'Rs 18,000.00', 'Eighteen Thousand Rupees Only', 'Rs 5,000.00', '01 Jul 2025', '30 Jun 2026']);
    Pdf::assertDontSee(['Rs 999.00', 'Rs 777.00', 'Rs 2,500.00']);
});

it('only certifies donors with a CNIC or NTN (BR-12)', function () {
    $donor = taxDonor(withCnic: false);
    paidOn($donor, '2025-09-01 10:00', '1000.00');

    certificates()->generate($donor, new FiscalYear(2025));
})->throws(DomainException::class, 'No CNIC or NTN on file');

it('accepts an NTN instead of a CNIC', function () {
    $donor = taxDonor(withCnic: false);
    $donor->update(['ntn' => '1234567-8']);
    paidOn($donor, '2025-09-01 10:00', '1000.00');

    expect(certificates()->generate($donor, new FiscalYear(2025))->total)->toBe('1000.00');
});

it('refuses a year with no donations', function () {
    certificates()->generate(taxDonor(), new FiscalYear(2025));
})->throws(DomainException::class, 'no paid donations in FY 2025-26');

it('keeps the same certificate when nothing changed, and reissues when something did', function () {
    $donor = taxDonor();
    $donation = paidOn($donor, '2025-09-01 10:00', '3000.00');
    $first = certificates()->generate($donor, new FiscalYear(2025));

    expect(certificates()->generate($donor, new FiscalYear(2025))->id)->toBe($first->id);

    app(DonationService::class)->refund($donation->fresh(), '500.00', 'Partial', PaymentMethod::Cash);
    Pdf::fake();
    $second = certificates()->generate($donor, new FiscalYear(2025));

    expect($second->id)->not->toBe($first->id)
        ->and($second->total)->toBe('2500.00')
        ->and($second->certificate_no)->toEndWith('-000002')
        ->and($first->fresh()->isVoid())->toBeTrue()
        ->and($first->fresh()->void_reason)->toContain('Reissued')
        ->and(TaxCertificate::query()->valid()->where('donor_id', $donor->id)->count())->toBe(1);
    Pdf::assertViewHas('void', true); // the old PDF was redrawn as void
    Pdf::assertViewHas('certificateNo', $second->certificate_no);
});

it('never changes or deletes a certificate', function (string $action) {
    $donor = taxDonor();
    paidOn($donor, '2025-09-01 10:00', '3000.00');
    $certificate = certificates()->generate($donor, new FiscalYear(2025));

    $action === 'update' ? $certificate->update(['total' => '1.00']) : $certificate->delete();
})->with(['update', 'delete'])->throws(DomainException::class);

it('keeps the full CNIC out of the stored certificate', function () {
    $donor = taxDonor();
    paidOn($donor, '2025-09-01 10:00', '3000.00');

    $certificate = certificates()->generate($donor, new FiscalYear(2025));
    $middle = substr((string) $donor->cnic, 6, 7);

    expect(json_encode($certificate->snapshot))->not->toContain($middle)
        ->and($certificate->snapshot['donor']['cnic'])->toBe($donor->maskedCnic());
});

// ── a whole year ────────────────────────────────────────────────────────

it('queues certificates for every eligible donor in the year and lists who was skipped', function () {
    $a = taxDonor();
    $b = taxDonor();
    $noCnic = taxDonor(withCnic: false);
    $otherYear = taxDonor();
    paidOn($a, '2025-08-01 10:00', '1000.00');
    paidOn($b, '2026-02-01 10:00', '2000.00');
    paidOn($noCnic, '2025-10-01 10:00', '3000.00');
    paidOn($otherYear, '2024-10-01 10:00', '4000.00');
    Queue::fake();

    $result = certificates()->generateForYear(new FiscalYear(2025));

    expect($result['queued'])->toBe(2)
        ->and($result['skipped'])->toHaveKey($noCnic->id)
        ->and($result['skipped'][$noCnic->id])->toContain('No CNIC or NTN');
    Queue::assertPushed(GenerateTaxCertificate::class, 2);
    Queue::assertPushed(GenerateTaxCertificate::class, fn (GenerateTaxCertificate $j): bool => $j->donorId === $a->id && $j->fiscalYear === 2025);
});

it('generates from the command line', function () {
    $donor = taxDonor();
    paidOn($donor, '2025-08-01 10:00', '1000.00');

    $this->artisan('tax-certificates:generate', ['year' => 2025, '--now' => true])
        ->expectsOutputToContain('1 certificate(s) ready')
        ->assertSuccessful();

    expect(TaxCertificate::query()->sole()->donor_id)->toBe($donor->id);
});

// ── sending ─────────────────────────────────────────────────────────────

it('sends a certificate to the donor with the PDF, and never a void one', function () {
    $donor = taxDonor();
    paidOn($donor, '2025-08-01 10:00', '1000.00');
    $certificate = certificates()->generate($donor, new FiscalYear(2025));

    $logs = certificates()->send($certificate);

    // The returned log is from the moment it was queued; the send happened after — re-read it.
    $email = collect($logs)->first(fn (MessageLog $l): bool => $l->channel->value === 'email')?->fresh();
    expect($email?->status)->toBe(MessageStatus::Sent)
        ->and($email?->email_subject)->toBe("Your tax certificate for FY 2025-26 — {$certificate->certificate_no}")
        ->and($email?->attachment_name)->toBe("{$certificate->certificate_no}.pdf")
        ->and($certificate->fresh()->sent_at)->not->toBeNull();

    certificates()->void($certificate, 'Wrong donor');
    expect(fn () => certificates()->send($certificate->fresh()))->toThrow(DomainException::class, 'is void');
});

// ── admin screen ────────────────────────────────────────────────────────

it('lets each role open the tax certificates as the matrix allows', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/tax-certificates')->assertStatus($status);
})->with([
    'finance' => [Role::Finance, 200],
    'manager (view)' => [Role::Manager, 200],
    'auditor (view)' => [Role::Auditor, 200],
    'campaign manager' => [Role::CampaignManager, 403],
]);

it('lets Finance generate a year, then send the certificates in bulk', function () {
    $a = taxDonor();
    $b = taxDonor();
    paidOn($a, '2025-08-01 10:00', '1000.00');
    paidOn($b, '2025-09-01 10:00', '2000.00');
    paidOn(taxDonor(withCnic: false), '2025-09-01 10:00', '500.00');
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ListTaxCertificates::class)
        ->callAction('generate', data: ['year' => 2025])
        ->assertNotified('FY 2025-26: 2 certificate(s) being prepared');

    $certs = TaxCertificate::query()->get();
    expect($certs)->toHaveCount(2)
        ->and($certs->whereNotNull('sent_at'))->toHaveCount(0); // generated, not sent

    Livewire::test(ListTaxCertificates::class)
        ->selectTableRecords($certs->pluck('id')->all())
        ->callAction(TestAction::make('sendSelected')->table()->bulk())
        ->assertNotified('2 certificate(s) sent');

    expect(TaxCertificate::query()->whereNotNull('sent_at')->count())->toBe(2);
});

it('reissues from the screen only when donations changed', function () {
    $donor = taxDonor();
    paidOn($donor, '2025-08-01 10:00', '1000.00');
    $certificate = certificates()->generate($donor, new FiscalYear(2025));
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ListTaxCertificates::class)
        ->callAction(TestAction::make('reissue')->table($certificate))
        ->assertNotified('Already up to date');

    expect(TaxCertificate::query()->count())->toBe(1);
});

it('lets view-only roles download but not generate, send or reissue', function () {
    $donor = taxDonor();
    paidOn($donor, '2025-08-01 10:00', '1000.00');
    $certificate = certificates()->generate($donor, new FiscalYear(2025));
    $this->actingAs(staffUser(Role::Manager));

    Livewire::test(ListTaxCertificates::class)
        ->assertActionHidden('generate')
        ->assertActionVisible(TestAction::make('download')->table($certificate))
        ->assertActionHidden(TestAction::make('send')->table($certificate))
        ->assertActionHidden(TestAction::make('reissue')->table($certificate))
        ->callAction(TestAction::make('download')->table($certificate))
        ->assertFileDownloaded("{$certificate->certificate_no}.pdf");
});
