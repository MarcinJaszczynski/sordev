<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Filament\Resources\EventResource\RelationManagers;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Imprezy';
    protected static ?string $navigationLabel = 'Imprezy';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Podstawowe informacje')
                    ->schema([
                        Forms\Components\Select::make('event_template_id')
                            ->label('Szablon imprezy')
                            ->relationship('eventTemplate', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Wybierz szablon, na podstawie którego zostanie utworzona impreza'),
                        
                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa imprezy')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Wprowadź nazwę imprezy dla klienta'),
                        
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Szkic',
                                'confirmed' => 'Potwierdzona',
                                'in_progress' => 'W trakcie',
                                'completed' => 'Zakończona',
                                'cancelled' => 'Anulowana',
                            ])
                            ->default('draft')
                            ->required(),
                    ]),
                
                Forms\Components\Section::make('Informacje o kliencie')
                    ->schema([
                        Forms\Components\TextInput::make('client_name')
                            ->label('Nazwa klienta')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('client_email')
                            ->label('Email klienta')
                            ->email()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('client_phone')
                            ->label('Telefon klienta')
                            ->tel()
                            ->maxLength(20),
                    ]),
                
                Forms\Components\Section::make('Szczegóły imprezy')
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Data rozpoczęcia')
                            ->required()
                            ->native(false),
                        
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Data zakończenia')
                            ->native(false)
                            ->after('start_date'),

                        Forms\Components\TextInput::make('duration_days')
                            ->label('Liczba dni')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->helperText('Obliczana automatycznie na podstawie dat lub kopiowana z szablonu'),
                        
                        Forms\Components\TextInput::make('participant_count')
                            ->label('Liczba uczestników')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        
                        Forms\Components\TextInput::make('total_cost')
                            ->label('Całkowity koszt (PLN)')
                            ->numeric()
                            ->prefix('PLN')
                            ->default(0)
                            ->readOnly()
                            ->helperText('Koszt jest obliczany automatycznie na podstawie punktów programu'),
                        
                        Forms\Components\Select::make('assigned_to')
                            ->label('Przypisany do')
                            ->relationship('assignedUser', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ]),

                Forms\Components\Section::make('Transport i logistyka')
                    ->schema([
                        Forms\Components\TextInput::make('transfer_km')
                            ->label('Kilometry transferu')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Kopiowane z szablonu, można edytować'),

                        Forms\Components\TextInput::make('program_km')
                            ->label('Kilometry programu')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Kopiowane z szablonu, można edytować'),

                        Forms\Components\Select::make('bus_id')
                            ->label('Autokar')
                            ->relationship('bus', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Kopiowany z szablonu, można zmienić'),

                        Forms\Components\Select::make('start_place_id')
                            ->label('Miejsce wyjazdu')
                            ->relationship('startPlace', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Wybierz miejsce wyjazdu dla tej imprezy'),

                        Forms\Components\Select::make('markup_id')
                            ->label('Narzut')
                            ->relationship('markup', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Kopiowany z szablonu, można zmienić'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa imprezy')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                
                Tables\Columns\TextColumn::make('eventTemplate.name')
                    ->label('Szablon')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('client_name')
                    ->label('Klient')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Data rozpoczęcia')
                    ->date('d.m.Y')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Data zakończenia')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('Jednodniowa'),
                
                Tables\Columns\TextColumn::make('participant_count')
                    ->label('Uczestnicy')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Koszt')
                    ->money('PLN')
                    ->sortable()
                    ->alignEnd(),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'secondary' => 'draft',
                        'success' => 'confirmed',
                        'warning' => 'in_progress',
                        'primary' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Szkic',
                        'confirmed' => 'Potwierdzona',
                        'in_progress' => 'W trakcie',
                        'completed' => 'Zakończona',
                        'cancelled' => 'Anulowana',
                        default => $state,
                    }),
                
                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Przypisany do')
                    ->placeholder('Nie przypisano')
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzona')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Szkic',
                        'confirmed' => 'Potwierdzona',
                        'in_progress' => 'W trakcie',
                        'completed' => 'Zakończona',
                        'cancelled' => 'Anulowana',
                    ]),
                
                Tables\Filters\SelectFilter::make('event_template_id')
                    ->label('Szablon')
                    ->relationship('eventTemplate', 'name')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\Filter::make('start_date')
                    ->label('Data rozpoczęcia')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('start_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('start_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Event $record) => $record->status === 'draft'),
                
                Tables\Actions\Action::make('change_status')
                    ->label('Zmień status')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Nowy status')
                            ->options([
                                'draft' => 'Szkic',
                                'confirmed' => 'Potwierdzona',
                                'in_progress' => 'W trakcie',
                                'completed' => 'Zakończona',
                                'cancelled' => 'Anulowana',
                            ])
                            ->required(),
                        
                        Forms\Components\Textarea::make('reason')
                            ->label('Powód zmiany')
                            ->rows(3),
                    ])
                    ->action(function (Event $record, array $data) {
                        $record->changeStatus($data['status'], $data['reason'] ?? null);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ProgramPointsRelationManager::class,
            RelationManagers\HistoryRelationManager::class,
            RelationManagers\SnapshotsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
            'edit-program' => Pages\EditEventProgram::route('/{record}/program'),
            'calculation' => Pages\EventCalculation::route('/{record}/calculation'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'in_progress')->count();
    }
}
