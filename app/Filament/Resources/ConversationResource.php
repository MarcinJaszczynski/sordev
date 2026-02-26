<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConversationResource\Pages;
use App\Models\Conversation;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Czat';
    protected static ?string $navigationGroup = 'Komunikacja';
    protected static ?string $modelLabel = 'rozmowa';
    protected static ?string $pluralModelLabel = 'rozmowy';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Nazwa rozmowy')
                    ->maxLength(255)
                    ->required()
                    ->helperText('Nadaj nazwę tej rozmowie, np. "Projekt ABC", "Spotkanie w sprawie..."'),
                Forms\Components\Select::make('type')
                    ->label('Typ rozmowy')
                    ->options([
                        'private' => 'Prywatna (1-na-1)',
                        'group' => 'Grupowa',
                    ])
                    ->default('private')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                        // Jeśli zmieniono na private, ogranicz uczestników do 1
                        if ($state === 'private') {
                            $currentParticipants = $get('participants');
                            if (is_array($currentParticipants) && count($currentParticipants) > 1) {
                                $set('participants', [reset($currentParticipants)]);
                            }
                        }
                    }),
                Forms\Components\Select::make('participants')
                    ->label('Uczestnicy')
                    ->multiple()
                    ->options(User::all()->pluck('name', 'id'))
                    ->required()
                    ->minItems(1)
                    ->maxItems(fn (Forms\Get $get) => $get('type') === 'private' ? 1 : 50)
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                        // Jeśli zmieniono na private i wybrano więcej niż 1 użytkownika, ogranicz do 1
                        if ($get('type') === 'private' && is_array($state) && count($state) > 1) {
                            $set('participants', [reset($state)]);
                        }
                    })
                    ->helperText(fn (Forms\Get $get) => $get('type') === 'private' 
                        ? 'Wybierz jednego użytkownika dla rozmowy 1-na-1' 
                        : 'Wybierz wielu użytkowników dla rozmowy grupowej')
                    ->searchable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['participants']))
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Nazwa')
                    ->formatStateUsing(function ($record) {
                        return $record->getDisplayName(\Illuminate\Support\Facades\Auth::user());
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'private' => 'gray',
                        'group' => 'primary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'private' => 'Prywatna',
                        'group' => 'Grupowa',
                    }),
                Tables\Columns\TextColumn::make('participants_count')
                    ->label('Uczestnicy')
                    ->counts('participants'),
                Tables\Columns\TextColumn::make('messages_count')
                    ->label('Wiadomości')
                    ->counts('messages'),
                Tables\Columns\TextColumn::make('last_message_at')
                    ->label('Ostatnia wiadomość')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzona')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Typ rozmowy')
                    ->options([
                        'private' => 'Prywatna',
                        'group' => 'Grupowa',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('open_chat')
                    ->label('Otwórz czat')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn (Conversation $record): string => '/admin/chat?conversation=' . $record->id),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('last_message_at', 'desc');
    }

    public static function getTableQuery(): Builder
    {
        // Pokaż tylko rozmowy w których uczestniczy zalogowany użytkownik
        return parent::getTableQuery()
            ->whereHas('participants', function ($query) {
                $query->where('user_id', \Illuminate\Support\Facades\Auth::id());
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConversations::route('/'),
            'create' => Pages\CreateConversation::route('/create'),
            'edit' => Pages\EditConversation::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
