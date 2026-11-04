<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Support\AdminPalette;

// The colourful admin theme: brand crimson + one bright colour per area, on every panel page.

it('adds the colourful theme to the login page', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('--imd-sidebar', false)
        ->assertSee('.fi-simple-layout', false);
});

it('colours the dashboard cards and the sidebar groups', function () {
    $this->actingAs(staffUser(Role::SuperAdmin))
        ->get('/admin')
        ->assertOk()
        ->assertSee('imd-stat imd-stat-crimson', false)
        ->assertSee('imd-stat imd-stat-emerald', false)
        ->assertSee('[data-group-label="Donations"]', false)
        ->assertSee(AdminPalette::CRIMSON, false);
});

it('builds gradient classes for every tone and falls back to slate', function () {
    expect(AdminPalette::stat('violet'))->toBe(['class' => 'imd-stat imd-stat-violet'])
        ->and(AdminPalette::stat('nope'))->toBe(['class' => 'imd-stat imd-stat-slate'])
        ->and(AdminPalette::alpha('#e5254b', 0.15))->toBe('rgba(229, 37, 75, 0.15)')
        ->and(AdminPalette::series(10))->toBe(AdminPalette::SERIES[0]);
});
