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
                fn (): string => view('filament.components.admin-readability-styles')->render()
                    .view('filament.pilot.components.portal-styles')->render(),
            )
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                function (): string {
                    $html = '';

                    if (\App\Http\Middleware\PilotPreviewMiddleware::isActive()) {
                        $banner = app(\App\Services\PilotAccessService::class)->previewBannerContext()
                            ?? [
                                'title' => 'Podgląd portalu pilota',
                                'description' => 'Tryb podglądu biurowego — tylko odczyt.',
                                'exitUrl' => url('/pilot/pilot-events?exit_preview=1'),
                            ];
                        $html .= view('filament.components.preview-mode-banner', [
                            'title' => $banner['title'],
                            'description' => $banner['description'],
                            'exitUrl' => $banner['exitUrl'] ?? null,
                            'accentClass' => 'border-blue-200 bg-blue-50 text-blue-950',
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
