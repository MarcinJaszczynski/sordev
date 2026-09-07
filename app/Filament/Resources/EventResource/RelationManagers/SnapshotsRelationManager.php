<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\EventSnapshot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista migawek imprezy: zapis, podgląd, porównanie z obecnym stanem.
 * Przywracanie stanu celowo poza zakresem (operacja destrukcyjna).
 */
class SnapshotsRelationManager extends RelationManager
{
    protected static string $relationship = 'snapshots';

    protected static ?string $title = 'Migawki imprezy';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nazwa migawki')
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('creator'))
            ->columns([
                Tables\Columns\TextColumn::make('snapshot_date')
                    ->label('Data')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'original' => 'Pierwotny',
                        'manual' => 'Ręczny',
                        'status_change' => 'Zmiana statusu',
                        default => ucfirst((string) $state),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'original' => 'info',
                        'manual' => 'success',
                        'status_change' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Opis')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? strip_tags($state) : '—')
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_cost_snapshot')
                    ->label('Koszt całkowity')
                    ->money('PLN')
                    ->sortable()
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('price_per_person')
                    ->label('Cena / os.')
                    ->state(fn (EventSnapshot $record): ?float => $record->pricePerPersonSnapshot())
                    ->money('PLN')
                    ->placeholder('—')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Utworzył')
                    ->placeholder('System')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('calculations')
                    ->label('Punkty programu')
                    ->formatStateUsing(function (?array $state): string {
                        $pointsCount = (int) ($state['points_count'] ?? 0);
                        $activeCount = (int) ($state['active_points_count'] ?? 0);

                        return "{$activeCount}/{$pointsCount} aktywnych";
                    })
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Typ')
                    ->options([
                        'original' => 'Pierwotny',
                        'manual' => 'Ręczny',
                        'status_change' => 'Zmiana statusu',
                    ]),
                Tables\Filters\Filter::make('snapshot_date')
                    ->label('Data utworzenia')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Od'),
                        Forms\Components\DatePicker::make('until')->label('Do'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('snapshot_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('snapshot_date', '<=', $date),
                            );
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_manual_snapshot')
                    ->label('Zapisz migawkę')
                    ->icon('heroicon-o-camera')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa migawki')
                            ->required()
                            ->maxLength(255)
                            ->default(fn (): string => 'Stan przed zmianami klienta '.now()->format('d.m.Y')),
                        Forms\Components\Textarea::make('description')
                            ->label('Opis')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Opcjonalnie: kontekst zmian od klienta.'),
                    ])
                    ->action(function (array $data): void {
                        $this->getOwnerRecord()->createManualSnapshot(
                            $data['name'],
                            filled($data['description'] ?? null) ? (string) $data['description'] : null,
                        );

                        Notification::make()
                            ->title('Migawka zapisana')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('Szczegóły')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Szczegóły migawki')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zamknij')
                    ->modalContent(fn (EventSnapshot $record) => view('filament.modals.snapshot-details', [
                        'snapshot' => $record->loadMissing('creator'),
                    ]))
                    ->modalWidth('7xl'),
                Tables\Actions\Action::make('compare_with_current')
                    ->label('Porównaj')
                    ->icon('heroicon-o-scale')
                    ->color('info')
                    ->modalHeading('Porównanie z obecnym stanem')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zamknij')
                    ->modalContent(function (EventSnapshot $record) {
                        return view('filament.modals.snapshot-comparison', [
                            'snapshot' => $record,
                            'comparison' => $record->compareWithCurrent(),
                        ]);
                    })
                    ->modalWidth('7xl'),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Brak migawek')
            ->emptyStateDescription('Zapisz stan imprezy przed zmianami od klienta — potem porównasz go z bieżącą kalkulacją.')
            ->emptyStateIcon('heroicon-o-camera');
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
