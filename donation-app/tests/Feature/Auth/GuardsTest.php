<?php

declare(strict_types=1);

use App\Models\Donor;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// FR-ACC-01, FR-DNR-01 — staff and donors are separate user types.

it('logs a donor in through the donor guard', function () {
    $donor = Donor::factory()->create(['email' => 'donor@example.com']);

    expect(Auth::guard('donor')->attempt(['email' => 'donor@example.com', 'password' => 'password']))->toBeTrue()
        ->and(Auth::guard('donor')->id())->toBe($donor->id);
});

it('does not accept donor credentials on the staff guard', function () {
    Donor::factory()->create(['email' => 'donor@example.com']);

    expect(Auth::guard('web')->attempt(['email' => 'donor@example.com', 'password' => 'password']))->toBeFalse();
});

it('does not accept staff credentials on the donor guard', function () {
    User::factory()->create(['email' => 'staff@example.com']);

    expect(Auth::guard('donor')->attempt(['email' => 'staff@example.com', 'password' => 'password']))->toBeFalse();
});

it('does not let a guest donor (no password) log in', function () {
    Donor::factory()->guest()->create(['email' => 'guest@example.com']);

    expect(Auth::guard('donor')->attempt(['email' => 'guest@example.com', 'password' => 'password']))->toBeFalse();
});

it('lets an active staff user into the admin panel', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertOk();
});

it('blocks an inactive staff user from the admin panel', function () {
    $this->actingAs(User::factory()->inactive()->create())->get('/admin')->assertForbidden();
});

it('does not let a logged-in donor into the admin panel', function () {
    $this->actingAs(Donor::factory()->create(), 'donor')->get('/admin')->assertRedirect('/admin/login');
});
