<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('file_path')
                    ->label('Plik')
                    ->required()
                    ->directory('task-attachments')
                    ->storeFileNamesIn('name')
                    ->preserveFilenames(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')
                ->label('Nazwa pliku')
                ->searchable()
                ->url(fn ($record) => $record->public_url)
                ->openUrlInNewTab(),
            Tables\Columns\TextColumn::make('user.name')
                ->label('Dodane przez'),
            Tables\Columns\TextColumn::make('readable_size')
                ->label('Rozmiar')
                ->placeholder('—'),
            Tables\Columns\TextColumn::make('created_at')
                ->label('Data dodania')
                ->dateTime(),
        ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();
                        $data['mime_type'] = $data['file_path'] ? Storage::mimeType($data['file_path']) : null;
                        $data['size'] = $data['file_path'] ? Storage::size($data['file_path']) : null;
                        $data['name'] = $data['name'] ?? basename((string) ($data['file_path'] ?? ''));

                        return $data;
                    }),
            ])->actions([
                Tables\Actions\Action::make('download')
                    ->label('Pobierz')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record) => $record->public_url)
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
