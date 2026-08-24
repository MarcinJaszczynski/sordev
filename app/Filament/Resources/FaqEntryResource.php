<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FaqEntryResource\Pages;
use App\Models\EventTemplate;
use App\Models\FaqEntry;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FaqEntryResource extends Resource
{
    protected static ?string $model = FaqEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'FAQ SEO';

    protected static ?string $modelLabel = 'wpis FAQ';

    protected static ?string $pluralModelLabel = 'FAQ SEO';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_SYSTEM;

    protected static ?int $navigationSort = 45;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Pytanie i odpowiedź')
                ->schema([
                    Forms\Components\TextInput::make('question')
                        ->label('Pytanie')
                        ->required()
                        ->maxLength(500)
                        ->columnSpanFull(),
                    \FilamentTiptapEditor\TiptapEditor::make('answer')
                        ->label('Odpowiedź')
                        ->required()
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Publikacja')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('category')
                        ->label('Kategoria')
                        ->options(FaqEntry::categoryLabels())
                        ->required(),
                    Forms\Components\Select::make('scope')
                        ->label('Zakres wyświetlania')
                        ->options(FaqEntry::scopeLabels())
                        ->required(),
                    Forms\Components\Select::make('event_template_id')
                        ->label('Przypisana wycieczka (opcjonalnie)')
                        ->searchable()
                        ->nullable()
                        ->options(fn () => EventTemplate::query()->orderBy('name')->pluck('name', 'id')),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Kolejność')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_published')
                        ->label('Opublikowane')
                        ->default(true),
                    Forms\Components\Toggle::make('include_in_schema')
                        ->label('Uwzględnij w JSON-LD FAQPage')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('question')->searchable()->limit(60),
                Tables\Columns\TextColumn::make('category')
                    ->label('Kategoria')
                    ->formatStateUsing(fn ($state) => FaqEntry::categoryLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('scope')
                    ->label('Zakres')
                    ->formatStateUsing(fn ($state) => FaqEntry::scopeLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('eventTemplate.name')
                    ->label('Wycieczka')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_published')->boolean()->label('Aktywne'),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFaqEntries::route('/'),
            'create' => Pages\CreateFaqEntry::route('/create'),
            'edit' => Pages\EditFaqEntry::route('/{record}/edit'),
        ];
    }
}
