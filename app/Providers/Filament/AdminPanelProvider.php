<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Widgets\LastImportWidget;
use App\Filament\Widgets\SalesOverviewWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The Mercatura admin panel (docs/02_V2B_ADMIN.md): one panel on /admin,
 * the storefront's web guard, access for users with the admin role.
 * Identity comes from config/brand.php; copy from lang/it/admin.php.
 */
final class AdminPanelProvider extends PanelProvider
{
    /** Sections placed side by side in a grid stretch to the height of the row. */
    private static function layoutStyles(): string
    {
        // Filament 5 markup: .fi-grid-col > .fi-sc-component > .fi-sc-section (flex column) > section.fi-section.
        // The grid column already stretches to the row; the wrappers and the section follow it, so the
        // sections of a row (e.g. the order's Riepilogo and Totali) end at the same height.
        return '<style>.fi-grid{align-items:stretch}.fi-grid>.fi-grid-col>.fi-sc-component,.fi-grid>.fi-grid-col>.fi-sc-component>.fi-sc-section{height:100%}.fi-sc-section>.fi-section{flex:1 1 auto;display:flex;flex-direction:column}.fi-section>.fi-section-content-ctn{flex:1 1 auto}</style>';
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authGuard('web')
            ->login()
            ->brandName(fn (): string => (string) config('brand.name'))
            ->brandLogo(fn (): string => asset((string) config('brand.logo')))
            ->brandLogoHeight('2rem')
            ->favicon(fn (): string => asset(rtrim((string) config('brand.favicon_path'), '/').'/favicon.ico'))
            ->colors([
                'primary' => Color::Blue,
            ])
            ->sidebarCollapsibleOnDesktop()
            // The storefront bundles Livewire with Vite and sets livewire.inject_assets
            // to false; the panel needs the runtime injected explicitly, where the
            // injection would have put it.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => Blade::render('@livewireStyles').self::layoutStyles())
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => Blade::render('@livewireScripts'))
            // The admin's own report to the helpdesk (docs/06_USER_REPORTS.md §2.9), only with a forwarder.
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn (): string => \App\Http\Livewire\Admin\ReportProblem::shown()
                ? Blade::render('@livewire(\App\Http\Livewire\Admin\ReportProblem::class)')
                : '')
            // Its screen capture (§5): the shared module, and the modals hidden while the frame is taken.
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => \App\Http\Livewire\Admin\ReportProblem::shown()
                ? Blade::render("@vite('resources/js/report-capture.js')").'<style>.mercatura-capturing .fi-modal{visibility:hidden}</style>'
                : '')
            ->navigationGroups([
                NavigationGroup::make(fn (): string => __('admin.nav.sales')),
                NavigationGroup::make(fn (): string => __('admin.nav.catalog')),
                NavigationGroup::make(fn (): string => __('admin.nav.content')),
                NavigationGroup::make(fn (): string => __('admin.nav.imports')),
                NavigationGroup::make(fn (): string => __('admin.nav.system')),
            ])
            ->navigationItems([
                NavigationItem::make(fn (): string => __('admin.common.storefront'))
                    ->url(fn (): string => route('frontend.home'), shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->group(fn (): string => __('admin.nav.system'))
                    ->sort(91),
            ])
            ->plugins(app(\App\Support\ImportConnectors::class)->adminPlugins())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                \App\Filament\Pages\Sitemap::class,
                \App\Filament\Pages\Reports::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                SalesOverviewWidget::class,
                LastImportWidget::class,
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
