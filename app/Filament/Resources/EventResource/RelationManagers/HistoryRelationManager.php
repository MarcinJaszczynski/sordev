<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\EventHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'history';

    protected static ?string $title = 'Historia zmian';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('action')
                    ->label('Akcja')
                    ->readOnly(),

                Forms\Components\TextInput::make('field')
                    ->label('Pole')
                    ->readOnly(),

                Forms\Components\Textarea::make('old_value')
                    ->label('Stara wartość')
                    ->readOnly(),

                Forms\Components\Textarea::make('new_value')
                    ->label('Nowa wartość')
                    ->readOnly(),

                \FilamentTiptapEditor\TiptapEditor::make('description')
                    ->label('Opis')
                    ->readOnly(),

                Forms\Components\TextInput::make('user.name')
                    ->label('Użytkownik')
                    ->readOnly(),

                Forms\Components\TextInput::make('ip_address')
                    ->label('Adres IP')
                    ->readOnly(),

                Forms\Components\TextInput::make('created_at')
                    ->label('Data')
                    ->readOnly(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data/Czas')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Użytkownik')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('action')
                    ->label('Akcja')
                    ->formatStateUsing(fn (EventHistory $record) => $record->readable_action)
                    ->colors([
                        'success' => ['created', 'program_added', 'program_copied'],
                        'warning' => ['updated', 'program_changed', 'program_moved'],
                        'danger' => ['deleted', 'program_removed'],
                        'info' => ['status_changed'],
                        'secondary' => 'default',
                    ]),

                Tables\Columns\TextColumn::make('field')
                    ->label('Pole')
                    ->placeholder('—')
                    ->limit(20),

                Tables\Columns\TextColumn::make('description')
                    ->label('Opis')
                    ->html(false)
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }

                        return $state;
                    }),

                Tables\Columns\TextColumn::make('formatted_old_value')
                    ->label('Stara wartość')
                    ->limit(30)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('formatted_new_value')
                    ->label('Nowa wartość')
                    ->limit(30)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('Akcja')
                    ->options([
                        'created' => 'Utworzono',
                        'updated' => 'Zaktualizowano',
                        'deleted' => 'Usunięto',
                        'program_changed' => 'Zmieniono program',
                        'program_added' => 'Dodano punkt programu',
                        'program_removed' => 'Usunięto punkt programu',
                        'program_moved' => 'Przeniesiono punkt programu',
                        'program_copied' => 'Skopiowano program',
                        'status_changed' => 'Zmieniono status',
                    ]),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Użytkownik')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('created_at')
                    ->label('Data')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Zobacz szczegóły'),
            ])
            ->bulkActions([
                // Brak akcji grupowych dla historii - tylko do odczytu
            ])
            ->emptyStateHeading('Brak historii zmian')
            ->emptyStateDescription('Historia zmian pojawi się po dokonaniu modyfikacji w imprezie.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
