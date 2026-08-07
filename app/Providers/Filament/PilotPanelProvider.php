<?php

namespace App\Providers\Filament;

use App\Support\PilotNavigation;
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

class PilotPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('pilot')
            ->path('pilot')
            ->login()
            ->brandName('Portal pilota')
            ->brandLogo(asset('images/bprafa-pilot-logo.svg'))
            ->brandLogoHeight('2.25rem')
            ->font('Inter')
            ->maxContentWidth(MaxWidth::Full)
            ->darkMode(condition: false, isForced: true)
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                'primary' => Color::hex('#0D9488'),
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])
            ->discoverResources(in: app_path('Filament/Pilot/Resources'), for: 'App\\Filament\\Pilot\\Resources')
            ->discoverPages(in: app_path('Filament/Pilot/Pages'), for: 'App\\Filament\\Pilot\\Pages')
            ->discoverWidgets(in: app_path('Filament/Pilot/Widgets'), for: 'App\\Filament\\Pilot\\Widgets')
            ->navigationGroups(collect(PilotNavigation::panelGroups())
                ->map(fn (bool $collapsed, string $label) => NavigationGroup::make($label)
                    ->label($label)
                    ->extraSidebarAttributes([
                        'class' => PilotNavigation::groupCssClass($label),
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
                \App\Http\Middleware\PilotPreviewMiddleware::class,
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
                fn (): string => view('filament.components.admin-readability-styles')->render(),
            )
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                function (): string {
                    $html = '';

                    if (\App\Http\Middleware\PilotPreviewMiddleware::isActive()) {
                        $html .= view('filament.components.preview-mode-banner', [
                            'title' => 'Podgląd portalu pilota',
                            'description' => 'Widzisz portal tak jak pilot wycieczki. To tryb podglądu biura — nie zastępuje logowania przypisanego pilota.',
                            'accentClass' => 'border-teal-200 bg-teal-50 text-teal-950',
                        ])->render();
                    }

                    $livewire = \Livewire\Livewire::current();

                    if (is_object($livewire) && method_exists($livewire, 'getPilotTripEvent')) {
                        $event = $livewire->getPilotTripEvent();
                        if ($event) {
                            $active = method_exists($livewire, 'getPilotTripNavActiveTab')
                                ? $livewire->getPilotTripNavActiveTab()
                                : null;
                            $html .= view('filament.pilot.components.pilot-trip-nav', [
                                'event' => $event,
                                'activeTab' => $active,
                            ])->render();
                        }
                    }

                    if (is_object($livewire) && method_exists($livewire, 'getWorkflowContext')) {
                        $context = $livewire->getWorkflowContext();
                        if (! empty($context)) {
                            $html .= view('filament.components.workflow-record-context', ['context' => $context])->render();
                        }
                    }

                    return $html;
                },
            );
    }
}
