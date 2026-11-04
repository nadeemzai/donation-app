<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Http\Middleware\RequireMfaForPrivilegedRoles;
use App\Http\Middleware\SecurityHeaders;
use App\Settings\GeneralSettings;
use App\Support\AdminPalette;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Sidebar groups, in display order. Resources set $navigationGroup to one of these.
     *
     * @var list<string>
     */
    public const NAVIGATION_GROUPS = [
        'Dashboard',
        'Donations',
        'Campaigns',
        'Donors',
        'Programs',
        'Seasonal',
        'Inventory',
        'Finance',
        'Content',
        'Settings',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(isSimple: false)
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ])
            // Name from Settings → General ("Imdaad"); same favicon as the public website.
            ->brandName(fn (): string => app(GeneralSettings::class)->org_name ?: 'Imdaad')
            ->favicon(asset('favicon.svg'))
            // Imdaad brand crimson (as on the website) + a bright colour per meaning.
            ->colors([
                'primary' => Color::hex(AdminPalette::CRIMSON),
                'success' => Color::Emerald,
                'info' => Color::Sky,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'gray' => Color::Slate,
                'violet' => Color::Violet,
                'teal' => Color::Teal,
                'pink' => Color::Pink,
            ])
            // Colourful sidebar, top bar, cards and login page (plain CSS, no build step).
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): View => view('filament.admin-theme'))
            ->darkMode(true)
            ->spa()
            ->databaseNotifications()
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups(self::NAVIGATION_GROUPS)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SecurityHeaders::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                RequireMfaForPrivilegedRoles::class,
            ]);
    }
}
