<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\EventSnapshot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SnapshotsRelationManager extends RelationManager
{
    protected static string $relationship = 'snapshots';
    protected static ?string $title = 'Snapshoty / Wersje imprezy';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nazwa snapshotu')
                    ->required()
                    ->maxLength(255),
                
                Forms\Components\Textarea::make('description')
                    ->label('Opis')
                    ->rows(3)
                    ->maxLength(500),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('snapshot_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('snapshot_date')
                    ->label('Data utworzenia')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Typ')
                    ->formatStateUsing(fn (EventSnapshot $record) => $record->readable_type)
                    ->colors([
                        'primary' => 'original',
                        'success' => 'manual',
                        'warning' => 'status_change',
                    ]),
                
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                
                Tables\Columns\TextColumn::make('description')
                    ->label('Opis')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),
                
                Tables\Columns\TextColumn::make('total_cost_snapshot')
                    ->label('Koszt całkowity')
                    ->money('PLN')
                    ->sortable()
                    ->alignEnd(),
                
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Utworzył')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('calculations')
                    ->label('Punkty programu')
                    ->formatStateUsing(function ($state) {
                        $pointsCount = $state['points_count'] ?? 0;
                        $activeCount = $state['active_points_count'] ?? 0;
                        return "{$activeCount}/{$pointsCount} aktywnych";
                    })
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Typ snapshotu')
                    ->options([
                        'original' => 'Pierwotny',
                        'manual' => 'Ręczny',
                        'status_change' => 'Zmiana statusu',
                    ]),
                
                Tables\Filters\Filter::make('snapshot_date')
                    ->label('Data utworzenia')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Od'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Do'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('snapshot_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('snapshot_date', '<=', $date),
                            );
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_manual_snapshot')
                    ->label('Utwórz snapshot')
                    ->icon('heroicon-o-camera')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa snapshotu')
                            ->required()
                            ->maxLength(255)
                            ->default('Snapshot ręczny ' . now()->format('d.m.Y H:i')),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('Opis')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Opisz powód utworzenia tego snapshotu'),
                    ])
                    ->action(function (array $data) {
                        $this->getOwnerRecord()->createManualSnapshot($data['name'], $data['description']);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('Szczegóły')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Szczegóły snapshotu')
                    ->modalContent(function (EventSnapshot $record) {
                        return view('filament.modals.snapshot-details', ['snapshot' => $record]);
                    })
                    ->modalWidth('7xl'),
                
                Tables\Actions\Action::make('compare_with_current')
                    ->label('Porównaj z obecnym')
                    ->icon('heroicon-o-scale')
                    ->color('info')
                    ->modalHeading('Porównanie z obecnym stanem')
                    ->modalContent(function (EventSnapshot $record) {
                        $comparison = $record->compareWithCurrent();
                        return view('filament.modals.snapshot-comparison', [
                            'snapshot' => $record,
                            'comparison' => $comparison,
                        ]);
                    })
                    ->modalWidth('7xl'),
                
                Tables\Actions\Action::make('restore')
                    ->label('Przywróć')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Przywróć snapshot')
                    ->modalDescription(function (EventSnapshot $record) {
                        return "Czy na pewno chcesz przywrócić imprezę do stanu ze snapshotu '{$record->name}'? Obecny stan zostanie zapisany jako backup.";
                    })
                    ->modalSubmitActionLabel('Tak, przywróć')
                    ->action(function (EventSnapshot $record) {
                        $record->restoreToEvent();
                    })
                    ->visible(fn (EventSnapshot $record) => $this->getOwnerRecord()->canBeEdited()),
                
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (EventSnapshot $record) => $record->type !== 'original'), // Nie można usunąć pierwotnego snapshotu
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            // Filtruj aby nie usunąć pierwotnego snapshotu
                            return $records->filter(fn ($record) => $record->type !== 'original');
                        }),
                ]),
            ])
            ->emptyStateHeading('Brak snapshotów')
            ->emptyStateDescription('Snapshoty pozwalają na zapisanie i przywrócenie stanu imprezy.')
            ->emptyStateIcon('heroicon-o-camera');
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
