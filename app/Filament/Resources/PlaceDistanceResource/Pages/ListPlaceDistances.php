<?php

namespace App\Filament\Resources\PlaceDistanceResource\Pages;

use App\Filament\Resources\PlaceDistanceResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Illuminate\Support\Collection;
use App\Models\PlaceDistance;
use App\Models\Place;
use App\Services\PlaceDistanceGenerator;

class ListPlaceDistances extends ListRecords
{
    protected static string $resource = PlaceDistanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
            \Filament\Actions\Action::make('fillAllowedMissingDistances')
                ->label('Uzupełnij brakujące (startowe)')
                ->icon('heroicon-o-sparkles')
                ->requiresConfirmation()
                ->action('fillAllowedMissingDistances'),
            \Filament\Actions\Action::make('pruneNonStartingPairs')
                ->label('Usuń pary nie-start/nie-start')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action('pruneNonStartingPairs'),
            \Filament\Actions\Action::make('updateDistances')
                ->label('Aktualizuj odległości z API')
                ->icon('heroicon-o-arrow-path')
                ->action('updateDistances'),
        ];
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
        Notification::make()->title("Uzupełniono brakujące odległości (startowe). Dodano: {$added}.")->success()->send();
    }

    public function pruneNonStartingPairs(): void
    {
        // Delete distances where both endpoints are NOT starting places.
        // NOTE: Avoid pluck()->whereIn() here - SQLite has a low bind parameter limit and will throw
        // "too many SQL variables" for large datasets.
        $query = PlaceDistance::query()
            ->whereHas('fromPlace', fn($q) => $q->where('starting_place', false))
            ->whereHas('toPlace', fn($q) => $q->where('starting_place', false));

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
        return [
            BulkActionGroup::make([
                BulkAction::make('recalculateSelected')
                    ->label('Przelicz zaznaczone (brakujące)')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $apiKey = config('services.openrouteservice.key') ?: '5b3ce3597851110001cf62489885073b636a44e3ac9774af529a3c40';
                        $updated = 0;
                        foreach ($records as $pd) {
                            if (!$pd instanceof PlaceDistance) {
                                $pd = PlaceDistance::find($pd);
                            }
                            if (!$pd || $pd->distance_km) {
                                continue;
                            }
                            $distance = $this->fetchDistance($pd->fromPlace, $pd->toPlace, $apiKey);
                            if ($distance !== null) {
                                $pd->update([
                                    'distance_km' => $distance,
                                    'api_source' => 'openrouteservice',
                                ]);
                                $updated++;
                            }
                        }
                        Notification::make()->title("Zaktualizowano $updated odległości.")->success()->send();
                    }),

                BulkAction::make('forceRecalculateSelected')
                    ->label('Wymuś przeliczenie zaznaczonych')
                    ->icon('heroicon-o-refresh')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $apiKey = config('services.openrouteservice.key') ?: '5b3ce3597851110001cf62489885073b636a44e3ac9774af529a3c40';
                        $updated = 0;
                        foreach ($records as $pd) {
                            if (!$pd instanceof PlaceDistance) {
                                $pd = PlaceDistance::find($pd);
                            }
                            if (!$pd) {
                                continue;
                            }
                            $distance = $this->fetchDistance($pd->fromPlace, $pd->toPlace, $apiKey);
                            if ($distance !== null) {
                                $pd->update([
                                    'distance_km' => $distance,
                                    'api_source' => 'openrouteservice',
                                ]);
                                $updated++;
                            }
                        }
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
                    ->action(function (Collection $records, array $data) {
                        $val = $data['distance_km'] ?? null;
                        $count = 0;
                        foreach ($records as $pd) {
                            if (!$pd instanceof PlaceDistance) {
                                $pd = PlaceDistance::find($pd);
                            }
                            if (!$pd) {
                                continue;
                            }
                            $pd->update([
                                'distance_km' => $val,
                                'api_source' => 'manual',
                            ]);
                            $count++;
                        }
                        Notification::make()->title("Zaktualizowano $count rekordów.")->success()->send();
                    }),
            ]),
        ];
    }

    public function updateDistances()
    {
        $apiKey = '5b3ce3597851110001cf62489885073b636a44e3ac9774af529a3c40';
        $places = \App\Models\Place::all();
        foreach ($places as $from) {
            foreach ($places as $to) {
                if ($from->id === $to->id) continue;

                // NEW RULE: compute only for pairs where at least one side is a starting place
                if (!$from->starting_place && !$to->starting_place) {
                    continue;
                }

                $existing = \App\Models\PlaceDistance::where('from_place_id', $from->id)->where('to_place_id', $to->id)->first();
                if ($existing && $existing->distance_km) continue;
                $distance = $this->fetchDistance($from, $to, $apiKey);
                if ($distance !== null) {
                    \App\Models\PlaceDistance::updateOrCreate([
                        'from_place_id' => $from->id,
                        'to_place_id' => $to->id,
                    ], [
                        'distance_km' => $distance,
                        'api_source' => 'openrouteservice',
                    ]);
                }
            }
        }
        \Filament\Notifications\Notification::make()
            ->title('Odległości zostały zaktualizowane z API.')
            ->success()
            ->send();
    }

    protected function fetchDistance($from, $to, $apiKey)
    {
        if (!$from->latitude || !$from->longitude || !$to->latitude || !$to->longitude) return null;
        $url = 'https://api.openrouteservice.org/v2/directions/driving-car?api_key=' . $apiKey . '&start=' . $from->longitude . ',' . $from->latitude . '&end=' . $to->longitude . ',' . $to->latitude;
        try {
            $response = file_get_contents($url);
            $data = json_decode($response, true);
            if (isset($data['features'][0]['properties']['segments'][0]['distance'])) {
                return round($data['features'][0]['properties']['segments'][0]['distance'] / 1000, 2);
            }
        } catch (\Exception $e) {
            return null;
        }
        return null;
    }
}
