<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventPriceDescriptionResource\Pages;
use App\Models\EventPriceDescription;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use FilamentTiptapEditor\TiptapEditor;

class EventPriceDescriptionResource extends Resource
{
    protected static ?string $model = EventPriceDescription::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-euro';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_DICTIONARIES;

    protected static ?string $navigationLabel = 'Opisy cen imprez';

    protected static ?string $modelLabel = 'opis ceny imprezy';

    protected static ?string $pluralModelLabel = 'opisy cen imprez';

    protected static ?int $navigationSort = 120;

    /**
     * Wspólny schemat formularza (CRUD + create/edit z selecta na szablonie).
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label('Nazwa')
                ->required()
                ->maxLength(255),
            TiptapEditor::make('description')
                ->label('Opis (możesz używać <b>, <ul>, <li> itd.)')
                ->required()
                ->columnSpanFull(),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Opis')
                    ->limit(80)
                    ->html()
                    ->wrap(),
                Tables\Columns\TextColumn::make('event_templates_count')
                    ->counts('eventTemplates')
                    ->label('Szablony')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Aktualizacja')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
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
            'index' => Pages\ListEventPriceDescriptions::route('/'),
            'create' => Pages\CreateEventPriceDescription::route('/create'),
            'edit' => Pages\EditEventPriceDescription::route('/{record}/edit'),
        ];
    }
}
