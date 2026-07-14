<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentResource\Pages;
use App\Filament\Resources\DocumentResource\RelationManagers;
use App\Models\Document;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Dokumenty';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_SYSTEM;

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('document_section_id')
                ->label('Sekcja')
                ->relationship('section', 'title')
                ->required(),

            Forms\Components\TextInput::make('title')->required()->maxLength(255),
            Forms\Components\TextInput::make('slug')->required()->maxLength(255),
            Forms\Components\Textarea::make('excerpt')->rows(3),
            \FilamentTiptapEditor\TiptapEditor::make('content')->label('Treść'),
            Forms\Components\Toggle::make('is_published')->label('Opublikowany')->default(true),
            Forms\Components\TextInput::make('order_number')->label('Kolejność')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Tytuł')->searchable(),
                TextColumn::make('section.title')->label('Sekcja'),
                BooleanColumn::make('is_published')->label('Opublikowany'),
                TextColumn::make('order_number')->label('Kolejność')->sortable(),
            ])
            ->defaultSort('order_number');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user instanceof \App\Models\User) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('create document');
    }
}
