<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BusResource\Pages;
use App\Models\Bus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BusResource extends Resource
{    protected static ?string $model = Bus::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Transport';
    protected static ?string $navigationLabel = 'Autokary';
    protected static ?int $navigationSort = 10;
    protected static ?string $modelLabel = 'autokar';
    protected static ?string $pluralModelLabel = 'autokary';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nazwa')
                ->required(),
            Forms\Components\Textarea::make('description')
                ->label('Opis')
                ->nullable(),
            Forms\Components\TextInput::make('capacity')
                ->label('Pojemność')
                ->numeric()
                ->default(55)
                ->required(),
            Forms\Components\TextInput::make('package_price_per_day')
                ->label('Cena za pakiet na dzień')
                ->numeric()
                ->required(),
            Forms\Components\TextInput::make('package_km_per_day')
                ->label('Ilość km w pakiecie na dzień')
                ->numeric()
                ->default(300)
                ->required(),
            Forms\Components\TextInput::make('extra_km_price')
                ->label('Cena za km poza pakietem')
                ->numeric()
                ->required(),
            Forms\Components\TextInput::make('currency')
                ->label('Waluta')
                ->default('PLN')
                ->required(),
            Forms\Components\Toggle::make('convert_to_pln')
                ->label('Przeliczaj na złotówki')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Nazwa')->sortable(),
            Tables\Columns\TextColumn::make('description')->label('Opis')->limit(40),
            Tables\Columns\TextColumn::make('capacity')->label('Pojemność')->sortable(),
            Tables\Columns\TextColumn::make('package_price_per_day')->label('Cena za pakiet na dzień')->sortable(),
            Tables\Columns\TextColumn::make('package_km_per_day')->label('Km w pakiecie')->sortable(),
            Tables\Columns\TextColumn::make('extra_km_price')->label('Cena za km poza pakietem')->sortable(),
            Tables\Columns\TextColumn::make('currency')
                ->label('Waluta')
                ->sortable(),
            Tables\Columns\IconColumn::make('convert_to_pln')
                ->label('Przeliczaj na złotówki')
                ->boolean(),
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
            'index' => Pages\ListBuses::route('/'),
            'create' => Pages\CreateBus::route('/create'),
            'edit' => Pages\EditBus::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = \App\Models\User::query()->find(\Illuminate\Support\Facades\Auth::id());
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view bus')) {
            return true;
        }
        return false;
    }
    public static function canView(
        $record
    ): bool {
        $user = \App\Models\User::query()->find(\Illuminate\Support\Facades\Auth::id());
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view bus')) {
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
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'create bus')) {
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
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'update bus')) {
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
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'delete bus')) {
            return true;
        }
        return false;
    }
}
