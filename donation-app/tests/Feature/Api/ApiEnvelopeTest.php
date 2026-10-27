<?php

declare(strict_types=1);

use App\Models\Donor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

// FR-API-01 — one envelope for every API response.

beforeEach(function () {
    // Test-only routes to exercise error rendering.
    Route::middleware('api')->prefix('api/v1/_test')->group(function () {
        Route::post('validate', fn (Request $request) => $request->validate(['name' => 'required|string']));
        Route::get('me', fn (Request $request) => ['id' => $request->user()?->getKey()])->middleware('auth:sanctum');
    });
});

it('returns the health check in the envelope', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonStructure(['success', 'data', 'message', 'errors', 'meta'])
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('errors', null);
});

it('returns validation errors as 422 in the envelope', function () {
    $this->postJson('/api/v1/_test/validate', [])
        ->assertStatus(422)
        ->assertJsonStructure(['success', 'data', 'message', 'errors', 'meta'])
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['name'], 'errors');
});

it('returns 401 in the envelope without a token', function () {
    $this->getJson('/api/v1/_test/me')
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('accepts a donor Sanctum token', function () {
    $donor = Donor::factory()->create();
    Sanctum::actingAs($donor);

    $this->getJson('/api/v1/_test/me')->assertOk()->assertJsonPath('id', $donor->id);
});

it('returns 404 in the envelope without leaking internals', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Not found.');
});
