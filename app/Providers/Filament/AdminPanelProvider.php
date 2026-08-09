<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ImportExportPanel;
use App\Support\FilamentNavigation;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Infolists\Components\TextEntry;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table as FilamentTable;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('bprafa')
            ->brandLogo(asset('images/bprafa-logo.svg'))
            ->brandLogoHeight('2rem')
            ->font('Inter')
            ->maxContentWidth(MaxWidth::Full)
            ->darkMode(condition: false, isForced: true)
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                'primary' => Color::hex('#B45309'),
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                ImportExportPanel::class,
            ])
            ->widgets([
                \App\Filament\Widgets\SorOverviewWidget::class,
                \App\Filament\Widgets\ContinueWorkWidget::class,
                \App\Filament\Widgets\FinanceModuleNavWidget::class,
                Widgets\AccountWidget::class,
                \App\Filament\Widgets\SitemapGeneratorWidget::class,
            ])
            ->plugin(FilamentShieldPlugin::make())
            ->navigationGroups(collect(FilamentNavigation::panelGroups())
                ->map(fn (bool $collapsed, string $label) => NavigationGroup::make($label)
                    ->label($label)
                    ->extraSidebarAttributes([
                        'class' => FilamentNavigation::groupCssClass($label),
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->bootUsing(function (Panel $panel): void {
                $richTextFieldNames = [
                    'description',
                    'desc',
                    'content',
                    'notes',
                    'note',
                    'office_notes',
                    'pilot_notes',
                    'driver_notes',
                    'event_description',
                    'office_description',
                    'seo_description',
                    'transport_notes',
                    'reason',
                    'review_notes',
                    'offer_response_notes',
                    'offer_modification_notes',
                    'pickup_place_details',
                    'admin_notes',
                    'opis',
                    'uwagi',
                ];

                $shouldRenderAsHtml = static function (string $field) use ($richTextFieldNames): bool {
                    foreach ($richTextFieldNames as $name) {
                        if ($field === $name || str_ends_with($field, '.'.$name)) {
                            return true;
                        }
                    }

                    return false;
                };

                FilamentTable::configureUsing(function (FilamentTable $table): void {
                    $table
                        ->striped()
                        ->persistFiltersInSession()
                        ->persistSearchInSession()
                        ->persistColumnSearchesInSession()
                        ->persistSortInSession();
                });

                TextColumn::configureUsing(function (TextColumn $column) use ($shouldRenderAsHtml): void {
                    if ($shouldRenderAsHtml($column->getName())) {
                        $column->html();
                    }
                });

                TextEntry::configureUsing(function (TextEntry $entry) use ($shouldRenderAsHtml): void {
                    if ($shouldRenderAsHtml($entry->getName())) {
                        $entry->html();
                    }
                });
            })
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.components.admin-readability-styles')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => view('filament.components.filament-action-modal-heal')->render(),
            )
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                function (): string {
                    $livewire = \Livewire\Livewire::current();
                    if (! is_object($livewire) || ! method_exists($livewire, 'getWorkflowContext')) {
                        return '';
                    }

                    $context = $livewire->getWorkflowContext();
                    if (empty($context)) {
                        return '';
                    }

                    return view('filament.components.workflow-record-context', [
                        'context' => $context,
                    ])->render();
                },
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                function (): string {
                    try {
                        $user = Auth::user();
                        if (! $user) {
                            return '';
                        }

                        $notificationData = \App\Services\NotificationService::getTopbarDataForUser(
                            $user->id,
                            \App\Services\NotificationService::TOPBAR_LIMIT_PER_TYPE,
                            \App\Services\NotificationService::TOPBAR_COMBINED_LIMIT,
                            \App\Services\NotificationService::TOPBAR_TASK_QUERY_LIMIT,
                        );
                        $counts = $notificationData['counts'];

                        return view('filament.components.topbar-notifications', [
                            'newTasksCount' => $counts['tasks'],
                            'unreadMessagesCount' => $counts['messages'],
                            'commentsCount' => $counts['comments'] ?? 0,
                            'newEventsCount' => $counts['new_events'] ?? 0,
                            'confirmedEventsCount' => $counts['confirmed_events'] ?? 0,
                            'pendingCancellationEventsCount' => $counts['pending_cancellation_events'] ?? 0,
                            'invoiceRequestsCount' => $counts['invoice_requests'] ?? 0,
                            'workCount' => $counts['work'] ?? (($counts['tasks'] ?? 0) + ($counts['comments'] ?? 0)),
                            'eventsCount' => $counts['events'] ?? (
                                ($counts['new_events'] ?? 0)
                                + ($counts['confirmed_events'] ?? 0)
                                + ($counts['pending_cancellation_events'] ?? 0)
                                + ($counts['invoice_requests'] ?? 0)
                            ),
                            'totalUnread' => $counts['total_unread'] ?? 0,
                            'canSeeInvoiceRequests' => $user->hasRole(['super_admin', 'admin', 'biuro', 'ksiegowosc']),
                            'notificationItems' => $notificationData['items'] ?? [],
                            'notificationItemsByType' => $notificationData['items_by_type'] ?? [],
                        ])->render();
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Topbar notifications render failed: '.$e->getMessage(), [
                            'exception' => $e,
                        ]);

                        return '';
                    }
                }
            );
    }
}
