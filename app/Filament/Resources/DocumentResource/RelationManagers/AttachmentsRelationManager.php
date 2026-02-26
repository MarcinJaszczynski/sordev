<?php

namespace App\Filament\Resources\DocumentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $recordTitleAttribute = 'original_name';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('original_name')->label('Nazwa pliku')->required(),
            Forms\Components\TextInput::make('order_number')->label('Kolejność')->numeric()->default(0),
            FileUpload::make('path')
                ->label('Plik')
                ->disk('public')
                ->directory('documents/attachments')
                ->preserveFilenames()
                ->getUploadedFileUsing(function ($file, $storedFileNames): ?array {
                    if (blank($file)) {
                        return null;
                    }

                    try {
                        $disk = Storage::disk('public');
                        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */

                        $size = $disk->exists($file) ? $disk->size($file) : 0;
                        $type = $disk->exists($file) ? $disk->mimeType($file) : null;
                    } catch (\Throwable $e) {
                        $size = 0;
                        $type = null;
                    }

                    return [
                        'name' => basename($file),
                        'size' => $size,
                        'type' => $type,
                        'url' => '/storage/' . ltrim($file, '/'),
                    ];
                })
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('original_name')->label('Nazwa'),
            Tables\Columns\TextColumn::make('mime_type')->label('Typ'),
            Tables\Columns\TextColumn::make('order_number')->label('Kolejność')->sortable(),
        ]);
    }
}
