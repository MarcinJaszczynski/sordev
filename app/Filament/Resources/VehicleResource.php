<?php

namespace App\Filament\Resources;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Filament\Concerns\AuthorizesWithShield;
use App\Filament\Forms\VehicleFields;
use App\Filament\Resources\VehicleResource\Pages;
use App\Models\Vehicle;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VehicleResource extends Resource
{
    use AuthorizesWithShield;

    protected static ?string $model = Vehicle::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_CONTACTS;

    protected static ?string $navigationLabel = 'Pojazdy';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'pojazd';

    protected static ?string $pluralModelLabel = 'pojazdy';

    protected static ?string $recordTitleAttribute = 'registration_number';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dane pojazdu')
                    ->icon('heroicon-o-truck')
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                    ->schema(VehicleFields::schema(includeContractor: true, includeMedia: false)),
                Forms\Components\Section::make('Zdjęcia, pliki i uwagi')
                    ->icon('heroicon-o-photo')
                    ->description('Galeria, dokumenty (PDF) i notatki operacyjne do floty.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema(VehicleFields::mediaAndNotesFields()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('primary_image')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(null)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('registration_number')
                    ->label('Rejestracja')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (VehicleType|string|null $state): string => $state instanceof VehicleType
                        ? $state->label()
                        : (VehicleType::tryFrom((string) $state)?->label() ?? (string) $state)),
                Tables\Columns\TextColumn::make('brand')
                    ->label('Marka')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('model')
                    ->label('Model')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('manufacture_year')
                    ->label('Rocznik')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('capacity')
                    ->label('Miejsca')
                    ->state(fn (Vehicle $record): ?string => $record->capacityLabel())
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('contractor.name')
                    ->label('Przewoźnik')
                    ->searchable()
                    ->placeholder('Ad-hoc')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_ad_hoc')
                    ->label('Ad-hoc')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (VehicleStatus|string|null $state): string => $state instanceof VehicleStatus
                        ? $state->label()
                        : (VehicleStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn (VehicleStatus|string|null $state): string => $state instanceof VehicleStatus
                        ? $state->color()
                        : (VehicleStatus::tryFrom((string) $state)?->color() ?? 'gray')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Typ')
                    ->options(VehicleType::options()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(VehicleStatus::options()),
                Tables\Filters\TernaryFilter::make('is_ad_hoc')
                    ->label('Ad-hoc')
                    ->boolean(),
                Tables\Filters\SelectFilter::make('contractor_id')
                    ->label('Przewoźnik')
                    ->relationship('contractor', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('registration_number');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['contractor']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicles::route('/'),
            'create' => Pages\CreateVehicle::route('/create'),
            'edit' => Pages\EditVehicle::route('/{record}/edit'),
        ];
    }
}
