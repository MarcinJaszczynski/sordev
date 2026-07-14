<?php

namespace App\Filament\Resources\ContractorResource\RelationManagers;

use App\Filament\Forms\ContractorLocationFields;
use App\Models\ContractorLocation;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'locations';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Miejsca prowadzenia';

    protected static ?string $modelLabel = 'miejsce prowadzenia';

    protected static ?string $pluralModelLabel = 'miejsca prowadzenia';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return false;
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof ContractorLocation) {
            return '';
        }

        return $record->shortLabel();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(ContractorLocationFields::schema())
            ->columns(4);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Oddział')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('Miasto')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Domyślne')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? 'Aktywne' : 'Nieaktywne')
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (! empty($data['is_primary'])) {
                            $this->getOwnerRecord()->locations()->update(['is_primary' => false]);
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data, ContractorLocation $record): array {
                        if (! empty($data['is_primary'])) {
                            $this->getOwnerRecord()
                                ->locations()
                                ->whereKeyNot($record->getKey())
                                ->update(['is_primary' => false]);
                        }

                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
