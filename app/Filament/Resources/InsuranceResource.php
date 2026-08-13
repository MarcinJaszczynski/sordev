<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InsuranceResource\Pages;
use App\Models\Insurance;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InsuranceResource extends Resource
{
    protected static ?string $model = Insurance::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Ubezpieczenia';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_CONFIG;

    protected static ?string $modelLabel = 'Ubezpieczenie';

    protected static ?string $pluralModelLabel = 'Ubezpieczenia';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dane ubezpieczenia')
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\Select::make('coverage_type')
                            ->label('Typ (NNW / KL)')
                            ->options(Insurance::coverageTypeOptions())
                            ->nullable()
                            ->visible(fn (): bool => \Illuminate\Support\Facades\Schema::hasColumn('insurances', 'coverage_type'))
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('price_per_person')
                            ->label('Cena za osobę (PLN)')
                            ->required()
                            ->numeric()
                            ->prefix('PLN')
                            ->step(0.01)
                            ->minValue(0)
                            ->columnSpan(1),
                        \FilamentTiptapEditor\TiptapEditor::make('description')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Parametry ubezpieczenia')
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->label('Aktywne')
                            ->default(true)
                            ->inline(false),
                        Forms\Components\Toggle::make('insurance_enabled')
                            ->label('Ubezpieczenie włączone')
                            ->inline(false),
                        Forms\Components\Toggle::make('insurance_per_day')
                            ->label('Za dzień')
                            ->inline(false),
                        Forms\Components\Toggle::make('insurance_per_person')
                            ->label('Za osobę')
                            ->inline(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('coverage_type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Insurance::coverageTypeLabel($state) ?? '—')
                    ->visible(fn (): bool => \Illuminate\Support\Facades\Schema::hasColumn('insurances', 'coverage_type')),
                Tables\Columns\TextColumn::make('price_per_person')
                    ->label('Cena za osobę')
                    ->money('PLN')
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Aktywne')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Zaktualizowano')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Aktywne')
                    ->boolean()
                    ->trueLabel('Tylko aktywne')
                    ->falseLabel('Tylko nieaktywne')
                    ->native(false),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Uprawnienia do widoczności resource w panelu
     */
    public static function canViewAny(): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view insurance')) {
            return true;
        }

        return false;
    }

    public static function canCreate(): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'create insurance')) {
            return true;
        }

        return false;
    }

    public static function canEdit($record): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'edit insurance')) {
            return true;
        }

        return false;
    }

    public static function canDelete($record): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'delete insurance')) {
            return true;
        }

        return false;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInsurances::route('/'),
            'create' => Pages\CreateInsurance::route('/create'),
            'edit' => Pages\EditInsurance::route('/{record}/edit'),
        ];
    }
}
