<?php

namespace App\Providers\Filament;

use App\Support\ClientNavigation;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Table as FilamentTable;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('portal')
            ->path('portal')
            ->login()
            ->brandName('BP RAFA')
            ->brandLogo(asset('uploads/logo.png'))
            ->brandLogoHeight('2.1rem')
            ->font('Inter')
            ->maxContentWidth(MaxWidth::SevenExtraLarge)
            ->darkMode(condition: false, isForced: true)
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                'primary' => Color::hex('#0663fc'),
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])
            ->discoverResources(in: app_path('Filament/Client/Resources'), for: 'App\\Filament\\Client\\Resources')
            ->discoverPages(in: app_path('Filament/Client/Pages'), for: 'App\\Filament\\Client\\Pages')
            ->navigationGroups(collect(ClientNavigation::panelGroups())
                ->map(fn (bool $collapsed, string $label) => NavigationGroup::make($label)
                    ->label($label)
                    ->extraSidebarAttributes([
                        'class' => ClientNavigation::groupCssClass($label),
                    ])
                    ->collapsed($collapsed))
                ->values()
                ->all())
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\ClientPreviewMiddleware::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->bootUsing(function (): void {
                FilamentTable::configureUsing(function (FilamentTable $table): void {
                    $table
                        ->striped()
                        ->persistFiltersInSession()
                        ->persistSearchInSession()
                        ->persistSortInSession();
                });
            })
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.components.admin-readability-styles')->render()
                    .view('filament.client.components.portal-styles')->render(),
            )
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                function (): string {
                    $html = '';

                    if (\App\Http\Middleware\ClientPreviewMiddleware::isActive()) {
                        $banner = app(\App\Services\ClientAccessService::class)->previewBannerContext()
                            ?? [
                                'title' => 'Podgląd portalu klienta',
                                'description' => 'Tryb podglądu biurowego.',
                                'exitUrl' => url('/portal/client-events?exit_preview=1'),
                            ];
                        $html .= view('filament.components.preview-mode-banner', [
                            'title' => $banner['title'],
                            'description' => $banner['description'],
                            'exitUrl' => $banner['exitUrl'] ?? null,
                            'accentClass' => 'portal-preview-banner',
                        ])->render();
                    }

                    $livewire = \Livewire\Livewire::current();

                    if (is_object($livewire) && method_exists($livewire, 'getClientTripEvent')) {
                        $event = $livewire->getClientTripEvent();
                        if ($event) {
                            $active = method_exists($livewire, 'getClientTripNavActiveTab')
                                ? $livewire->getClientTripNavActiveTab()
                                : null;
                            $html .= view('filament.client.components.client-trip-nav', [
                                'event' => $event,
                                'activeTab' => $active,
                            ])->render();
                        }
                    }

                    return $html;
                },
            );
    }
}
