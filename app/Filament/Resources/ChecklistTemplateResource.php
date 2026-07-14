<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChecklistTemplateResource\Pages;
use App\Models\ChecklistTemplate;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ChecklistTemplateResource extends Resource
{
    protected static ?string $model = ChecklistTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Szablony checklisty pilota';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_EVENTS;

    protected static ?string $modelLabel = 'Szablon checklisty';

    protected static ?string $pluralModelLabel = 'Szablony checklisty';

    protected static ?int $navigationSort = 80;

    protected static function canManage(): bool
    {
        return (bool) Auth::user()?->hasRole(['admin', 'super_admin', 'biuro']);
    }

    public static function canViewAny(): bool
    {
        return static::canManage();
    }

    public static function canCreate(): bool
    {
        return static::canManage();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManage();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManage();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Szablon')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nazwa szablonu')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktywny')
                    ->default(true),
                Forms\Components\Textarea::make('description')
                    ->label('Opis')
                    ->rows(2)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Kolejność')
                    ->numeric()
                    ->default(0),
            ])->columns(2),

            Forms\Components\Section::make('Punkty checklisty')->schema([
                Forms\Components\Repeater::make('items')
                    ->relationship()
                    ->label('')
                    ->orderColumn('sort_order')
                    ->reorderable()
                    ->collapsible()
                    ->cloneable()
                    ->defaultItems(1)
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->addActionLabel('Dodaj punkt')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Treść punktu')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->label('Dodatkowy opis (opcjonalnie)')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nazwa')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label('Punktów'),
                Tables\Columns\IconColumn::make('is_active')->label('Aktywny')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Kolejność')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')->label('Zmieniono')->dateTime('d.m.Y H:i')->sortable()->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Aktywny'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChecklistTemplates::route('/'),
            'create' => Pages\CreateChecklistTemplate::route('/create'),
            'edit' => Pages\EditChecklistTemplate::route('/{record}/edit'),
        ];
    }
}
