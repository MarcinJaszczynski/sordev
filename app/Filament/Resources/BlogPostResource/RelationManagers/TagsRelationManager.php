<?php

namespace App\Filament\Resources\BlogPostResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Tables\Actions\AttachAction;
use Filament\Resources\Tables\Actions\DetachAction;
use Filament\Resources\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TagsRelationManager extends RelationManager
{
    protected static string $relationship = 'tags';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->label('Nazwa')->required()->maxLength(255),
            TextInput::make('slug')
                ->label('Identyfikator URL')
                ->helperText('Fragment adresu tagu w URL.')
                ->required()
                ->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nazwa')->searchable(),
            TextColumn::make('slug')->label('Identyfikator URL'),
        ])->headerActions([
            AttachAction::make(),
        ])->actions([
            DetachAction::make(),
        ]);
    }
}
