<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Support\AdminPalette;

// Colourful dashboard: gradient stat cards and colourful charts — on the dashboard only.

it('colours the dashboard cards', function () {
    $this->actingAs(staffUser(Role::SuperAdmin))
        ->get('/admin')
        ->assertOk()
        ->assertSee('imd-stat imd-stat-crimson', false)
        ->assertSee('imd-stat imd-stat-emerald', false)
        ->assertSee('.imd-stat-violet', false);
});

it('leaves the menu and other screens in the standard colours', function () {
    $this->actingAs(staffUser(Role::SuperAdmin))
        ->get('/admin/donors')
        ->assertOk()
        ->assertDontSee('.imd-stat-crimson', false);

    $this->get('/admin/login')->assertDontSee('.imd-stat', false);
});

it('builds gradient classes for every tone and falls back to slate', function () {
    expect(AdminPalette::stat('violet'))->toBe(['class' => 'imd-stat imd-stat-violet'])
        ->and(AdminPalette::stat('nope'))->toBe(['class' => 'imd-stat imd-stat-slate'])
        ->and(AdminPalette::alpha('#e5254b', 0.15))->toBe('rgba(229, 37, 75, 0.15)')
        ->and(AdminPalette::series(10))->toBe(AdminPalette::SERIES[0]);
});
