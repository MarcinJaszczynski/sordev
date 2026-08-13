<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HotelRoomResource\Pages;
use App\Models\HotelRoom;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HotelRoomResource extends Resource
{
    protected static ?string $model = HotelRoom::class;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Pokoje hotelowe';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_CONFIG;

    protected static ?string $modelLabel = 'pokój hotelowy';

    protected static ?string $pluralModelLabel = 'pokoje hotelowe';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Podstawowe informacje')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nazwa')
                        ->required()
                        ->columnSpan(2),
                    Forms\Components\TextInput::make('capacity')
                        ->label('Pojemność (max osób)')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Maksymalna liczba osób w pokoju (np. z oferty hotelu).'),
                    Forms\Components\TextInput::make('standard')
                        ->label('Standard')
                        ->maxLength(100)
                        ->placeholder('Wpisz standard pokoju'),
                    Forms\Components\TextInput::make('people_count')
                        ->label('Ilość osób (kalkulacja)')
                        ->numeric()
                        ->required()
                        ->helperText('Liczba osób używana w kalkulacji i planie noclegów.')
                        ->columnSpan(1),
                    \FilamentTiptapEditor\TiptapEditor::make('description')
                        ->columnSpanFull(),
                    \FilamentTiptapEditor\TiptapEditor::make('notes')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Cennik i waluta')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    Forms\Components\TextInput::make('price')
                        ->label('Cena za pokój')
                        ->numeric()
                        ->required(),
                    Forms\Components\TextInput::make('currency')
                        ->label('Waluta')
                        ->default('PLN')
                        ->required(),
                    Forms\Components\Toggle::make('convert_to_pln')
                        ->label('Przeliczaj na złotówki')
                        ->default(true)
                        ->inline(false),
                ]),

            Forms\Components\Section::make('Tagi')
                ->schema([
                    Forms\Components\Select::make('tags')
                        ->label('Tagi')
                        ->multiple()
                        ->relationship('tags', 'name')
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Nazwa')->sortable(),
            Tables\Columns\TextColumn::make('capacity')->label('Pojemność')->sortable(),
            Tables\Columns\TextColumn::make('standard')->label('Standard')->sortable()->toggleable(),
            Tables\Columns\TextColumn::make('description')->label('Opis')->html(false)->limit(40),
            Tables\Columns\TextColumn::make('notes')->label('Uwagi')->html(false)->limit(40),
            Tables\Columns\TextColumn::make('people_count')->label('Ilość osób')->sortable(),
            Tables\Columns\TextColumn::make('price')->label('Cena za pokój')->sortable(),
            Tables\Columns\TextColumn::make('currency')->label('Waluta')->sortable(),
            Tables\Columns\IconColumn::make('convert_to_pln')->label('Przeliczaj na złotówki')->boolean(),
        ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHotelRooms::route('/'),
            'create' => Pages\CreateHotelRoom::route('/create'),
            'edit' => Pages\EditHotelRoom::route('/{record}/edit'),
        ];
    }

    // Uprawnienia analogicznie jak w innych resources
    public static function canViewAny(): bool
    {
        $user = \App\Models\User::query()->find(\Illuminate\Support\Facades\Auth::id());
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view hotelroom')) {
            return true;
        }

        return false;
    }

    public static function canView($record): bool
    {
        $user = \App\Models\User::query()->find(\Illuminate\Support\Facades\Auth::id());
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view hotelroom')) {
            return true;
        }

        return false;
    }

    public static function canCreate(): bool
    {
        $user = \App\Models\User::query()->find(\Illuminate\Support\Facades\Auth::id());
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'create hotelroom')) {
            return true;
        }

        return false;
    }

    public static function canEdit($record): bool
    {
        $user = \App\Models\User::query()->find(\Illuminate\Support\Facades\Auth::id());
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'update hotelroom')) {
            return true;
        }

        return false;
    }

    public static function canDelete($record): bool
    {
        $user = \App\Models\User::query()->find(\Illuminate\Support\Facades\Auth::id());
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'delete hotelroom')) {
            return true;
        }

        return false;
    }
}
