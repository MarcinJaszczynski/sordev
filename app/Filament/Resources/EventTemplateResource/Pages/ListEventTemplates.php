<?php

namespace App\Filament\Resources\EventTemplateResource\Pages;

use App\Actions\EventTemplates\CloneEventTemplateAction;
use App\Filament\Resources\EventTemplateResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ListEventTemplates extends ListRecords
{
    protected static string $resource = EventTemplateResource::class;

    public function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\EventTemplateResource\Widgets\PriceRecalcProgressWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected static string $defaultPaginationPageOption = '25';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Action::make('priceComparison')
                ->label('Porównanie cen')
                ->icon('heroicon-o-scale')
                ->color('gray')
                ->url(fn (): string => \App\Filament\Pages\EventTemplatePriceComparisonPage::getUrl())
                ->visible(fn (): bool => \App\Filament\Pages\EventTemplatePriceComparisonPage::canAccess()),
            Action::make('removeDuplicates')
                ->label('Usuń duplikaty cen')
                ->icon('heroicon-o-scissors')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    // Globalna deduplikacja
                    $duplicateGroups = \App\Models\EventTemplatePricePerPerson::select('event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id')
                        ->selectRaw('COUNT(*) as count')
                        ->groupBy('event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id')
                        ->having('count', '>', 1)
                        ->get();
                    $removed = 0;
                    foreach ($duplicateGroups as $group) {
                        $records = \App\Models\EventTemplatePricePerPerson::where([
                            'event_template_id' => $group->event_template_id,
                            'event_template_qty_id' => $group->event_template_qty_id,
                            'currency_id' => $group->currency_id,
                            'start_place_id' => $group->start_place_id,
                        ])->orderByDesc('id')->get();
                        for ($i = 1; $i < $records->count(); $i++) {
                            $records[$i]->delete();
                            $removed++;
                        }
                    }
                    \Filament\Notifications\Notification::make()
                        ->title('Deduplikacja zakończona')
                        ->body('Usunięto rekordów: '.$removed)
                        ->success()
                        ->send();
                }),
            Actions\Action::make('recalculateAllPrices')
                ->label('Przelicz ceny dla wszystkich')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    // Uruchom job w tle, aby nie blokować UI (po odpowiedzi)
                    $userId = (int) (Auth::id() ?? 0);
                    // zainicjuj stan postępu, żeby widget od razu się pojawił
                    \App\Services\PriceRecalcProgress::start($userId, \App\Models\EventTemplate::count());
                    \App\Jobs\RecalculateAllEventTemplatePricesJob::dispatch($userId);
                    $this->dispatch('priceRecalcStarted');

                    \Filament\Notifications\Notification::make()
                        ->title('Przeliczanie cen uruchomione')
                        ->body('Zadanie działa w tle. Po zakończeniu dostaniesz powiadomienie z podsumowaniem.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            TableAction::make('edit')
                ->url(fn ($record) => static::getResource()::getUrl('edit', ['record' => $record->id])),
            TableAction::make('delete'),
            TableAction::make('clone')
                ->label('Klonuj')
                ->icon('heroicon-o-document-duplicate')
                ->tooltip('Tworzy kopię szablonu z punktami programu, cenami i hotelami.')
                ->action(function ($record) {
                    $clone = app(CloneEventTemplateAction::class)($record);

                    Notification::make()
                        ->title('Szablon został pomyślnie sklonowany!')
                        ->success()
                        ->send();

                    return redirect(static::getResource()::getUrl('edit', ['record' => $clone->id]));
                }),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('recalculatePricesSelected')
                    ->label('Przelicz ceny (zaznaczone)')
                    ->icon('heroicon-o-calculator')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $ids = $records->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                        if (count($ids) === 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Nie wybrano żadnych szablonów')
                                ->warning()
                                ->send();

                            return;
                        }

                        $userId = (int) (Auth::id() ?? 0);
                        if ($userId <= 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Brak użytkownika')
                                ->body('Nie można zlecić przeliczenia bez zalogowanego użytkownika.')
                                ->danger()
                                ->send();

                            return;
                        }

                        // Zainicjuj progress dla użytkownika
                        \App\Services\PriceRecalcProgress::start($userId, count($ids));

                        $chunkSize = 250;
                        $chunks = array_chunk($ids, $chunkSize);
                        $lastChunkIndex = count($chunks) - 1;
                        foreach ($chunks as $index => $chunk) {
                            \App\Jobs\RecalculateSelectedEventTemplatePricesJob::dispatch(
                                $chunk,
                                $userId,
                                false,
                                $index === $lastChunkIndex,
                            );
                        }
                        $this->dispatch('priceRecalcStarted');

                        \Filament\Notifications\Notification::make()
                            ->title('Przeliczanie cen zlecone')
                            ->body('Szablony: '.count($ids).', paczki: '.count($chunks).'. Zadania wykonają się w tle.')
                            ->success()
                            ->send();
                    }),
                BulkAction::make('forceRecalculatePricesSelected')
                    ->label('WYMUSZ przeliczenie (zaznaczone)')
                    ->icon('heroicon-o-refresh')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $ids = $records->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                        if (count($ids) === 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Nie wybrano żadnych szablonów')
                                ->warning()
                                ->send();

                            return;
                        }

                        $userId = (int) (Auth::id() ?? 0);
                        if ($userId <= 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Brak użytkownika')
                                ->body('Nie można zlecić przeliczenia bez zalogowanego użytkownika.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $chunkSize = 100;
                        $chunks = array_chunk($ids, $chunkSize);
                        // Zainicjuj progress
                        \App\Services\PriceRecalcProgress::start($userId, count($ids));
                        $lastChunkIndex = count($chunks) - 1;
                        foreach ($chunks as $index => $chunk) {
                            \App\Jobs\RecalculateSelectedEventTemplatePricesJob::dispatch(
                                $chunk,
                                $userId,
                                true,
                                $index === $lastChunkIndex,
                            );
                        }
                        $this->dispatch('priceRecalcStarted');

                        \Filament\Notifications\Notification::make()
                            ->title('Wymuszone przeliczanie zlecone')
                            ->body('Szablony: '.count($ids).', paczki: '.count($chunks).'. Zadania wykonają się w tle.')
                            ->success()
                            ->send();
                    }),
            ]),
        ];
    }
}
