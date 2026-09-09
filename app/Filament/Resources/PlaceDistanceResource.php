<?php

namespace App\Filament\Resources;

use App\Filament\Pages\OpenRouteServiceSettingsPage;
use App\Filament\Resources\PlaceDistanceResource\Pages;
use App\Jobs\RecalculatePlaceDistancesJob;
use App\Models\Place;
use App\Models\PlaceDistance;
use App\Services\PlaceDistanceRouteService;
use App\Support\FilamentNavigation;
use App\Support\OpenRouteServiceSettings;
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
use Illuminate\Support\Collection;

class PlaceDistanceResource extends Resource
{
    protected static ?string $model = PlaceDistance::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-on-rectangle';

    protected static ?string $navigationLabel = 'Odległości między miejscami';

    protected static ?string $modelLabel = 'odległość między miejscami';

    protected static ?string $pluralModelLabel = 'odległości między miejscami';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_CONFIG;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('from_place_id')
                    ->label('Miejsce początkowe (podstawienia)')
                    ->options(fn (?PlaceDistance $record) => Place::startingPlaceSelectOptions(
                        (int) ($record?->from_place_id ?? 0) ?: null
                    ))
                    ->searchable()
                    ->required()
                    ->helperText('Tylko punkty startowe imprez.'),
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
                Select::make('api_source')
                    ->label('Źródło')
                    ->options(PlaceDistance::sourceLabels() + ['' => 'Brak źródła'])
                    ->helperText('Przy zapisie ręcznym ustaw „Ręcznie”. Przeliczenie ORS nadpisze źródło automatycznie.')
                    ->nullable(),
            ])
            ->columns(['default' => 1, 'md' => 2]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fromPlace.name')->label('Od')->searchable()->sortable(),
                TextColumn::make('toPlace.name')->label('Do')->searchable()->sortable(),
                TextColumn::make('distance_km')->label('Odległość (km)')->sortable(),
                TextColumn::make('api_source')
                    ->label('Źródło')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => PlaceDistance::sourceLabel($state))
                    ->color(fn (?string $state): string => PlaceDistance::sourceColor($state))
                    ->searchable()
                    ->sortable()
                    ->description(fn (PlaceDistance $record): ?string => $record->isFormulaEstimate()
                        ? 'Szacunek Haversine — do wymiany na trasę ORS'
                        : ($record->isOpenRoute() ? 'Rzeczywista trasa drogowa' : null)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('from_place_id')
                    ->label('Miejsce początkowe (podstawienia)')
                    ->options(fn () => Place::startingPlaceSelectOptions()),

                Tables\Filters\SelectFilter::make('to_place_id')
                    ->label('Miejsce docelowe')
                    ->options(Place::orderBy('name')->pluck('name', 'id')->toArray()),

                Tables\Filters\SelectFilter::make('api_source')
                    ->label('Źródło')
                    ->options(PlaceDistance::sourceLabels()),

                Tables\Filters\Filter::make('formula_only')
                    ->label('Tylko formuła (szacunki)')
                    ->query(fn (Builder $query) => $query->whereIn('api_source', [
                        PlaceDistanceRouteService::SOURCE_HAVERSINE,
                        PlaceDistanceRouteService::SOURCE_SYMMETRIC,
                    ])),

                Tables\Filters\Filter::make('ors_only')
                    ->label('Tylko OpenRouteService')
                    ->query(fn (Builder $query) => $query->where('api_source', PlaceDistanceRouteService::SOURCE_ORS)),

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
                            if (! OpenRouteServiceSettings::status()['has_api_key']) {
                                Notification::make()->title('Brak klucza ORS — ustaw w System → OpenRouteService')->danger()->send();

                                return;
                            }
                            $updated = app(PlaceDistanceRouteService::class)->recalculateCollection($records, false);
                            Notification::make()
                                ->title("Zaktualizowano $updated odległości")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('recalculate_force')
                        ->label('Wymuś przeliczenie zaznaczonych')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->modalDescription('Respektuje limity ORS z panelu. Przy dużej liczbie lepiej użyć kolejki.')
                        ->action(function (Collection $records) {
                            if (! OpenRouteServiceSettings::status()['has_api_key']) {
                                Notification::make()->title('Brak klucza ORS — ustaw w System → OpenRouteService')->danger()->send();

                                return;
                            }
                            if ($records->count() > 40) {
                                RecalculatePlaceDistancesJob::dispatch(force: true, estimatesOnly: false);
                                Notification::make()
                                    ->title('Zbyt dużo rekordów — uruchomiono kolejkę (brakujące/wszystkie wg joba)')
                                    ->warning()
                                    ->send();

                                return;
                            }
                            $updated = app(PlaceDistanceRouteService::class)->recalculateCollection($records, true);
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
                            $count = app(PlaceDistanceRouteService::class)
                                ->setManualDistance($records, (float) $data['distance_km']);
                            Notification::make()
                                ->title("Zapisano wartość dla $count rekordów")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('distance_km')
            ->headerActions([
                Tables\Actions\Action::make('ors_settings')
                    ->label('Ustawienia ORS')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(OpenRouteServiceSettingsPage::getUrl())
                    ->visible(fn (): bool => OpenRouteServiceSettingsPage::canAccess()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlaceDistances::route('/'),
            'create' => Pages\CreatePlaceDistance::route('/create'),
            'edit' => Pages\EditPlaceDistance::route('/{record}/edit'),
        ];
    }
}
