<?php

declare(strict_types=1);

use App\Enums\DonorType;
use App\Models\Donor;
use App\Support\Cnic;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// FR-DNR-01, FR-DNR-02, NFR-04

it('stores the CNIC encrypted and keeps a lookup hash', function () {
    $donor = Donor::factory()->create(['cnic' => '35202-1234567-1']);

    $raw = DB::table('donors')->where('id', $donor->id)->value('cnic');

    expect($raw)->not->toBe('35202-1234567-1')
        ->and($raw)->not->toContain('3520212345671')
        ->and($donor->fresh()->cnic)->toBe('35202-1234567-1')
        ->and($donor->cnic_hash)->toBe(Cnic::hash('3520212345671'));
});

it('treats differently formatted copies of the same CNIC as duplicates', function () {
    Donor::factory()->create(['cnic' => '35202-1234567-1']);
    Donor::factory()->create(['cnic' => '3520212345671']);
})->throws(QueryException::class);

it('stores the NTN encrypted', function () {
    $donor = Donor::factory()->corporate()->create(['ntn' => '1234567-8']);

    expect(DB::table('donors')->where('id', $donor->id)->value('ntn'))->not->toBe('1234567-8')
        ->and($donor->fresh()->ntn)->toBe('1234567-8');
});

it('requires an email or a phone number', function () {
    Donor::factory()->create(['email' => null, 'phone' => null]);
})->throws(DomainException::class);

it('accepts a donor with only a phone number', function () {
    $donor = Donor::factory()->guest()->create(['email' => null, 'phone' => '03001234567']);

    expect($donor->exists)->toBeTrue()->and($donor->isGuest())->toBeTrue();
});

it('provides guest, registered and corporate factory states', function () {
    expect(Donor::factory()->guest()->create()->isGuest())->toBeTrue()
        ->and(Donor::factory()->registered()->create()->email_verified_at)->not->toBeNull()
        ->and(Donor::factory()->corporate()->create()->type)->toBe(DonorType::Corporate);
});

it('hides sensitive fields from JSON', function () {
    $json = Donor::factory()->create(['cnic' => '35202-1234567-1'])->toArray();

    expect($json)->not->toHaveKeys(['password', 'cnic', 'cnic_hash', 'ntn', 'remember_token']);
});

it('validates CNIC format', function () {
    expect(Cnic::isValid('35202-1234567-1'))->toBeTrue()
        ->and(Cnic::isValid('12345'))->toBeFalse();
});
