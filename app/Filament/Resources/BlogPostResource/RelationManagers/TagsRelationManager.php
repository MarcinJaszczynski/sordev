<?php

namespace App\Filament\Resources\BlogPostResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Forms\Components\TextInput;
use Filament\Resources\Tables\Columns\TextColumn;
use Filament\Resources\Tables\Actions\DetachAction;
use Filament\Resources\Tables\Actions\AttachAction;
use Filament\Forms\Form;
use Filament\Tables\Table;

class TagsRelationManager extends RelationManager
{
    protected static string $relationship = 'tags';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('slug')->required()->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('slug')->label('Slug'),
        ])->headerActions([
            AttachAction::make(),
        ])->actions([
            DetachAction::make(),
        ]);
    }
}
