<?php

namespace App\Filament\Resources\ContractorResource\RelationManagers;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Filament\Forms\VehicleFields;
use App\Models\Vehicle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VehiclesRelationManager extends RelationManager
{
    protected static string $relationship = 'vehicles';

    protected static ?string $recordTitleAttribute = 'registration_number';

    protected static ?string $title = 'Pojazdy';

    protected static ?string $modelLabel = 'pojazd';

    protected static ?string $pluralModelLabel = 'pojazdy';

    protected static ?string $icon = 'heroicon-o-truck';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dane')
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                    ->schema(VehicleFields::schema(includeContractor: false, includeMedia: false)),
                Forms\Components\Section::make('Zdjęcia, pliki i uwagi')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema(VehicleFields::mediaAndNotesFields()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
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
                    ->label('Marka / model')
                    ->formatStateUsing(function ($state, Vehicle $record): string {
                        return trim(implode(' ', array_filter([(string) $record->brand, (string) $record->model]))) ?: '—';
                    }),
                Tables\Columns\TextColumn::make('manufacture_year')
                    ->label('Rocznik')
                    ->placeholder('—')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('capacity')
                    ->label('Miejsca')
                    ->alignCenter(),
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
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['is_ad_hoc'] = (bool) ($data['is_ad_hoc'] ?? false);

                        return $data;
                    }),
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
}
