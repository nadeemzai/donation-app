<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

// W12-D1 — permissions audit. These look at EVERY route and resource, so a screen or endpoint
// added later without a policy, a permission check or a rate limit fails here.

/**
 * Admin GET routes that need no URL parameter (lists, create pages, settings, reports…).
 *
 * @return list<string> route names
 */
function auditAdminScreens(): array
{
    $open = ['filament.admin.pages.dashboard']; // every staff member has a dashboard

    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $r): bool => str_starts_with((string) $r->getName(), 'filament.admin.')
            && in_array('GET', $r->methods(), true)
            && ! str_starts_with((string) $r->getName(), 'filament.admin.auth.')
            && ! in_array($r->getName(), $open, true)
            && preg_match('/\{[^}?]+\}/', $r->uri()) !== 1)
        ->map(fn (LaravelRoute $r): string => (string) $r->getName())
        ->values()
        ->all();
}

it('has a policy behind every admin resource', function () {
    $missing = collect(Filament::getPanel('admin')->getResources())
        ->filter(fn (string $resource): bool => Gate::getPolicyFor($resource::getModel()) === null)
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

it('shuts every admin screen to a staff member with no role', function () {
    (new RolesAndPermissionsSeeder)->run();
    $nobody = User::factory()->create(['is_active' => true]);
    $open = [];

    foreach (auditAdminScreens() as $name) {
        $status = $this->actingAs($nobody)->get(route($name))->getStatusCode();

        if ($status !== 403) {
            $open[] = "{$name} → {$status}";
        }
    }

    expect(auditAdminScreens())->not->toBeEmpty()
        ->and($open)->toBe([]);
});

it('sends guests to the login page from every admin screen', function () {
    $open = [];

    foreach (auditAdminScreens() as $name) {
        $response = $this->get(route($name));

        if (! $response->isRedirect(route('filament.admin.auth.login'))) {
            $open[] = "{$name} → {$response->getStatusCode()}";
        }
    }

    expect($open)->toBe([]);
});

it('keeps a deactivated staff member out of the panel', function () {
    (new RolesAndPermissionsSeeder)->run();
    $gone = User::factory()->create(['is_active' => false]);
    $gone->assignRole('super_admin');

    $this->actingAs($gone)->get('/admin')->assertForbidden();
});

it('protects every API endpoint that is not public on purpose', function () {
    $public = [
        'health', 'app.config', 'banners', 'funds', 'campaigns.index', 'campaigns.show', 'campaigns.updates',
        'impact-updates', 'projects.index', 'projects.show', 'projects.updates', 'project-updates',
        'qurbani.show', 'qurbani.bookings.store', 'pages.show', 'zakat.calculate',
        'auth.register', 'auth.login', 'auth.otp.send', 'auth.otp.verify', 'donations.store',
    ];
    $unprotected = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $name = (string) $route->getName();

        if (! str_starts_with($name, 'api.v1.') || in_array(substr($name, 7), $public, true)) {
            continue;
        }

        $middleware = $route->gatherMiddleware();

        if (! in_array('auth:sanctum', $middleware, true) && ! in_array('signed', $middleware, true)) {
            $unprotected[] = $name;
        }
    }

    expect($unprotected)->toBe([]);
});

it('rate-limits every public endpoint that writes or sends something', function () {
    $allowed = ['donor.logout']; // logging out is harmless
    // Framework routes with their own protection:
    //  horizon.*                — Super Admin only (viewHorizon gate, see HorizonServiceProvider)
    //  *livewire*.update        — CSRF + each component's own authorisation (admin panel, forms)
    //  storage.local.upload     — temporary signed upload URLs only
    $vendor = fn (string $name): bool => str_starts_with($name, 'horizon.') || str_contains($name, 'livewire') || $name === 'storage.local.upload';
    $unlimited = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $name = (string) $route->getName();
        $writes = array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods()) !== [];

        if (! $writes || $name === '' || str_starts_with($name, 'filament.') || str_starts_with($name, 'livewire.') || in_array($name, $allowed, true) || $vendor($name)) {
            continue;
        }

        $middleware = $route->gatherMiddleware();
        $throttled = collect($middleware)->contains(fn (mixed $m): bool => is_string($m) && str_starts_with($m, 'throttle'));
        $signedIn = collect($middleware)->contains(fn (mixed $m): bool => is_string($m) && str_starts_with($m, 'auth'));

        if (! $throttled && ! $signedIn) {
            $unlimited[] = $name;
        }
    }

    expect($unlimited)->toBe([]);
});

it('keeps private files off the public disk', function () {
    expect(config('filesystems.disks.local.root'))->not->toStartWith(public_path())
        ->and(config('filesystems.disks.local.serve'))->toBeTrue(); // signed URLs only
});

it('keeps the Horizon queue dashboard for Super Admins only', function () {
    $this->actingAs(staffUser(Role::Finance))->get('/horizon')->assertForbidden();
    $this->get('/horizon')->assertForbidden();
});
