<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlaceDistanceResource\Pages;
use App\Models\Place;
use App\Models\PlaceDistance;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
// use Filament\Tables\Columns\NumberColumn; (not needed)
use Illuminate\Support\Collection;

class PlaceDistanceResource extends Resource
{
    protected static ?string $model = PlaceDistance::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-on-rectangle';

    protected static ?string $navigationLabel = 'Odległości między miejscami';

    protected static ?string $navigationGroup = 'Ustawienia ogólne';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('from_place_id')
                    ->label('Miejsce początkowe')
                    ->options(Place::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('to_place_id')
                    ->label('Miejsce docelowe')
                    ->options(Place::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('distance_km')
                    ->label('Odległość drogowa (km)')
                    ->numeric()
                    ->minValue(0)
                    ->nullable(),
                TextInput::make('api_source')
                    ->label('Źródło API')
                    ->nullable(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fromPlace.name')->label('Od')->searchable()->sortable(),
                TextColumn::make('toPlace.name')->label('Do')->searchable()->sortable(),
                TextColumn::make('distance_km')->label('Odległość (km)')->sortable(),
                TextColumn::make('api_source')->label('Źródło')->searchable()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('from_place_id')
                    ->label('Miejsce początkowe')
                    ->options(Place::orderBy('name')->pluck('name', 'id')->toArray()),

                Tables\Filters\SelectFilter::make('to_place_id')
                    ->label('Miejsce docelowe')
                    ->options(Place::orderBy('name')->pluck('name', 'id')->toArray()),

                Tables\Filters\SelectFilter::make('api_source')
                    ->label('Źródło API')
                    ->options(PlaceDistance::query()->whereNotNull('api_source')->distinct()->pluck('api_source', 'api_source')->filter()->toArray()),

                Tables\Filters\Filter::make('has_distance')
                    ->label('Ma odległość')
                    ->query(fn (Builder $query) => $query->whereNotNull('distance_km')),
                Tables\Filters\Filter::make('no_distance')
                    ->label('Brak odległości')
                    ->query(fn (Builder $query) => $query->whereNull('distance_km')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('recalculate_missing')
                        ->label('Przelicz zaznaczone (brakujące)')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $updated = static::recalculateCollection($records, false);
                            Notification::make()
                                ->title("Zaktualizowano $updated odległości")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('recalculate_force')
                        ->label('Wymuś przeliczenie zaznaczonych')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $updated = static::recalculateCollection($records, true);
                            Notification::make()
                                ->title("Przeliczono $updated odległości")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('set_manual_distance')
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
                            $count = static::setManualDistance($records, (float) $data['distance_km']);
                            Notification::make()
                                ->title("Zapisano wartość dla $count rekordów")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('distance_km');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlaceDistances::route('/'),
            'create' => Pages\CreatePlaceDistance::route('/create'),
            'edit' => Pages\EditPlaceDistance::route('/{record}/edit'),
        ];
    }

    protected static function recalculateCollection(Collection $records, bool $force = false): int
    {
        $apiKey = config('services.openrouteservice.key') ?: '5b3ce3597851110001cf62489885073b636a44e3ac9774af529a3c40';
        $updated = 0;

        foreach ($records as $record) {
            if (! $record instanceof PlaceDistance) {
                $record = PlaceDistance::find($record);
            }

            if (! $record) {
                continue;
            }

            if (! $force && $record->distance_km) {
                continue;
            }

            $record->loadMissing(['fromPlace', 'toPlace']);
            $distance = static::fetchDistance($record->fromPlace, $record->toPlace, $apiKey);

            if ($distance === null) {
                continue;
            }

            $record->update([
                'distance_km' => $distance,
                'api_source' => 'openrouteservice',
            ]);

            $updated++;
        }

        return $updated;
    }

    protected static function setManualDistance(Collection $records, float $value): int
    {
        $count = 0;

        foreach ($records as $record) {
            if (! $record instanceof PlaceDistance) {
                $record = PlaceDistance::find($record);
            }

            if (! $record) {
                continue;
            }

            $record->update([
                'distance_km' => $value,
                'api_source' => 'manual',
            ]);

            $count++;
        }

        return $count;
    }

    public static function fetchDistance(?Place $from, ?Place $to, string $apiKey): ?float
    {
        if (! $from || ! $to || ! $from->latitude || ! $from->longitude || ! $to->latitude || ! $to->longitude) {
            return null;
        }

        $url = 'https://api.openrouteservice.org/v2/directions/driving-car?api_key='.$apiKey
            .'&start='.$from->longitude.','.$from->latitude
            .'&end='.$to->longitude.','.$to->latitude;

        try {
            $response = file_get_contents($url);
            $data = json_decode($response, true);
            if (isset($data['features'][0]['properties']['segments'][0]['distance'])) {
                return round($data['features'][0]['properties']['segments'][0]['distance'] / 1000, 2);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}
