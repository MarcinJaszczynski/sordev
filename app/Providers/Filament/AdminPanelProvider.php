<?php

namespace App\Providers\Filament;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\ImportExportPanel;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Table as FilamentTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Widgets;
use Filament\Navigation\NavigationGroup;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Auth;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
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
            ->maxContentWidth(MaxWidth::Full)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                ImportExportPanel::class,
            ])
            ->widgets([
                Widgets\AccountWidget::class,
                \App\Filament\Widgets\TaskCalendarWidget::class,
                \App\Filament\Widgets\SitemapGeneratorWidget::class,
            ])
            ->plugin(FilamentShieldPlugin::make())
            ->navigationGroups([
                NavigationGroup::make('Imprezy')
                    ->label('Imprezy')
                    ->collapsed(false),
                NavigationGroup::make('Szablony imprez')
                    ->label('Szablony imprez')
                    ->collapsed(false),
                NavigationGroup::make('Zadania')
                    ->label('Zadania')
                    ->collapsed(false),
                NavigationGroup::make('Finanse')
                    ->label('Finanse')
                    ->collapsed(false),
                NavigationGroup::make('Kontakty')
                    ->label('Kontakty')
                    ->collapsed(),
                NavigationGroup::make('Komunikacja')
                    ->label('Komunikacja')
                    ->collapsed(),
                NavigationGroup::make('Ustawienia kalkulacji')
                    ->label('Ustawienia kalkulacji')
                    ->collapsed(),
                NavigationGroup::make('Ustawienia ogólne')
                    ->label('Ustawienia ogólne')
                    ->collapsed(),
                NavigationGroup::make('Ustawienia noclegów')
                    ->label('Ustawienia noclegów')
                    ->collapsed(),
                NavigationGroup::make('Ustawienia transportu')
                    ->label('Ustawienia transportu')
                    ->collapsed(),
                NavigationGroup::make('Narzędzia')
                    ->label('Narzędzia')
                    ->collapsed(),
                NavigationGroup::make('Biblioteka mediów')
                    ->label('Biblioteka mediów')
                    ->collapsed(),
                NavigationGroup::make('Ustawienia')
                    ->label('Ustawienia')
                    ->collapsed(),
                NavigationGroup::make('Admin')
                    ->label('Admin')
                    ->collapsed(),
            ])
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
                        if ($field === $name || str_ends_with($field, '.' . $name)) {
                            return true;
                        }
                    }

                    return false;
                };

                FilamentTable::configureUsing(function (FilamentTable $table): void {
                    $table
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
                PanelsRenderHook::TOPBAR_END,
                function (): string {
                    $user = Auth::user();
                    if (!$user) {
                        return '';
                    }

                    $notificationData = \App\Services\NotificationService::getTopbarDataForUser($user->id);
                    $counts = $notificationData['counts'];
                    
                    return view('filament.components.topbar-notifications', [
                        'newTasksCount' => $counts['tasks'],
                        'unreadMessagesCount' => $counts['messages'],
                        'commentsCount' => $counts['comments'] ?? 0,
                        'newEventsCount' => $counts['new_events'] ?? 0,
                        'confirmedEventsCount' => $counts['confirmed_events'] ?? 0,
                        'pendingCancellationEventsCount' => $counts['pending_cancellation_events'] ?? 0,
                        'importantCount' => $counts['important'] ?? 0,
                        'notificationItems' => $notificationData['items'] ?? [],
                        'notificationItemsByType' => $notificationData['items_by_type'] ?? [],
                    ])->render();
                }
            );
    }
}
