<?php

namespace App\Filament\Resources\PlaceDistanceResource\Pages;

use App\Filament\Pages\OpenRouteServiceSettingsPage;
use App\Filament\Resources\PlaceDistanceResource;
use App\Jobs\RecalculatePlaceDistancesJob;
use App\Models\Place;
use App\Models\PlaceDistance;
use App\Services\PlaceDistanceGenerator;
use App\Services\PlaceDistanceRouteService;
use App\Support\OpenRouteServiceSettings;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Illuminate\Support\Collection;

class ListPlaceDistances extends ListRecords
{
    protected static string $resource = PlaceDistanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
            \Filament\Actions\Action::make('orsSettings')
                ->label('OpenRouteService')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(OpenRouteServiceSettingsPage::getUrl())
                ->visible(fn (): bool => OpenRouteServiceSettingsPage::canAccess()),
            \Filament\Actions\Action::make('fillAllowedMissingDistances')
                ->label('Uzupełnij brakujące (formuła)')
                ->icon('heroicon-o-sparkles')
                ->requiresConfirmation()
                ->modalDescription('Dodaje brakujące pary jako szacunek Haversine (Formuła). Potem możesz je wymienić na ORS.')
                ->action('fillAllowedMissingDistances'),
            \Filament\Actions\Action::make('pruneNonStartingPairs')
                ->label('Usuń pary nie-start/nie-start')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action('pruneNonStartingPairs'),
            \Filament\Actions\Action::make('queueMissingOrs')
                ->label('Kolejka: brakujące → ORS')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Uzupełnij brakujące trasami drogowymi')
                ->modalDescription('Job w kolejce respektuje limity z System → OpenRouteService.')
                ->action('queueMissingOrs'),
            \Filament\Actions\Action::make('queueEstimatesOrs')
                ->label('Kolejka: formuła → ORS')
                ->icon('heroicon-o-map')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Nadpisz szacunki Haversine trasami ORS')
                ->modalDescription('Zmieni źródło z „Formuła” na „OpenRouteService (trasa)”. Respektuje limity dzienne/minutowe.')
                ->action('queueEstimatesOrs'),
        ];
    }

    public function getSubheading(): ?string
    {
        $usage = app(PlaceDistanceRouteService::class)->usageSnapshot();
        $formula = (int) PlaceDistance::query()->whereIn('api_source', [
            PlaceDistanceRouteService::SOURCE_HAVERSINE,
            PlaceDistanceRouteService::SOURCE_SYMMETRIC,
        ])->count();
        $ors = (int) PlaceDistance::query()->where('api_source', PlaceDistanceRouteService::SOURCE_ORS)->count();

        return "Źródła: formuła {$formula} · ORS {$ors} · dziś API {$usage['daily_used']}/{$usage['daily_limit']}";
    }

    public function fillAllowedMissingDistances(): void
    {
        $before = PlaceDistance::count();
        $generator = app(PlaceDistanceGenerator::class);
        $places = Place::all();

        foreach ($places as $place) {
            $generator->generateForPlace($place);
        }

        $after = PlaceDistance::count();
        $added = max(0, $after - $before);
        Notification::make()
            ->title("Uzupełniono brakujące (formuła Haversine). Dodano: {$added}.")
            ->success()
            ->send();
    }

    public function pruneNonStartingPairs(): void
    {
        $query = PlaceDistance::query()
            ->whereHas('fromPlace', fn ($q) => $q->where('starting_place', false))
            ->whereHas('toPlace', fn ($q) => $q->where('starting_place', false));

        try {
            $count = (clone $query)->count();
            if ($count > 0) {
                $query->delete();
            }

            Notification::make()->title("Usunięto {$count} par nie-start/nie-start.")->success()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Nie udało się usunąć par nie-start/nie-start.')->danger()->send();
        }
    }

    protected function getTableBulkActions(): array
    {
        $routes = app(PlaceDistanceRouteService::class);

        return [
            BulkActionGroup::make([
                BulkAction::make('recalculateSelected')
                    ->label('Przelicz zaznaczone (brakujące)')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) use ($routes) {
                        if (! OpenRouteServiceSettings::status()['has_api_key']) {
                            Notification::make()->title('Brak klucza ORS')->danger()->send();

                            return;
                        }
                        $updated = $routes->recalculateCollection($records, false);
                        Notification::make()->title("Zaktualizowano $updated odległości.")->success()->send();
                    }),

                BulkAction::make('forceRecalculateSelected')
                    ->label('Wymuś przeliczenie zaznaczonych')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) use ($routes) {
                        if (! OpenRouteServiceSettings::status()['has_api_key']) {
                            Notification::make()->title('Brak klucza ORS')->danger()->send();

                            return;
                        }
                        $updated = $routes->recalculateCollection($records, true);
                        Notification::make()->title("Przeliczono $updated odległości.")->success()->send();
                    }),

                BulkAction::make('setDistanceForSelected')
                    ->label('Ustaw wartość dla zaznaczonych')
                    ->icon('heroicon-o-pencil')
                    ->form([
                        TextInput::make('distance_km')
                            ->label('Odległość (km)')
                            ->numeric()
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Collection $records, array $data) use ($routes) {
                        $count = $routes->setManualDistance($records, (float) ($data['distance_km'] ?? 0));
                        Notification::make()->title("Zaktualizowano $count rekordów.")->success()->send();
                    }),
            ]),
        ];
    }

    public function queueMissingOrs(): void
    {
        if (! OpenRouteServiceSettings::status()['has_api_key']) {
            Notification::make()
                ->title('Brak klucza ORS')
                ->body('Ustaw klucz w System → OpenRouteService')
                ->danger()
                ->send();

            return;
        }

        RecalculatePlaceDistancesJob::dispatch(force: false, estimatesOnly: false);
        Notification::make()->title('Dodano job: brakujące → ORS')->success()->send();
    }

    public function queueEstimatesOrs(): void
    {
        if (! OpenRouteServiceSettings::status()['has_api_key']) {
            Notification::make()
                ->title('Brak klucza ORS')
                ->body('Ustaw klucz w System → OpenRouteService')
                ->danger()
                ->send();

            return;
        }

        RecalculatePlaceDistancesJob::dispatch(force: true, estimatesOnly: true);
        Notification::make()->title('Dodano job: formuła → ORS')->success()->send();
    }
}
