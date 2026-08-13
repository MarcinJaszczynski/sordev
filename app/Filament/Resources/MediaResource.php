<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaResource\Pages;
use App\Models\Media;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_CONFIG;

    protected static ?string $navigationLabel = 'Multimedia';

    protected static ?string $modelLabel = 'plik multimedialny';

    protected static ?string $pluralModelLabel = 'multimedia';

    protected static ?int $navigationSort = 60;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('Tytuł'),
            Forms\Components\TextInput::make('alt')->label('Tekst alternatywny'),
            Forms\Components\Textarea::make('caption')->label('Podpis')->rows(2),
            \FilamentTiptapEditor\TiptapEditor::make('description'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('path')
                    ->label('Podgląd')
                    ->disk(fn ($record) => $record->disk)
                    ->height(64)
                    ->width(64),
                Tables\Columns\TextColumn::make('filename')
                    ->label('Nazwa')
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('disk')->options([
                    'public' => 'public',
                ]),
                Tables\Filters\SelectFilter::make('extension')->label('Typ')->options([
                    'jpg' => 'jpg', 'jpeg' => 'jpeg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('open')
                    ->label('Otwórz')
                    ->url(fn ($record) => $record->url())
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedia::route('/'),
            'create' => Pages\CreateMedia::route('/create'),
            'edit' => Pages\EditMedia::route('/{record}/edit'),
        ];
    }
}
