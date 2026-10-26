<?php

declare(strict_types=1);

use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Facades\Filament;

it('shows the branded admin login page', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Donation Management');
});

it('redirects guests from the admin panel to the login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets a logged-in staff user open the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertOk();
});

it('registers the navigation groups in the agreed order', function () {
    $groups = collect(Filament::getPanel('admin')->getNavigationGroups())
        ->map(fn ($group) => is_string($group) ? $group : $group->getLabel())
        ->values()
        ->all();

    expect($groups)->toBe(AdminPanelProvider::NAVIGATION_GROUPS);
});
