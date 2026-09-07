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

    protected static ?string $title = 'Załączniki';

    public bool $panelMode = false;

    protected function dispatchPanelUpdated(): void
    {
        if (! $this->panelMode) {
            return;
        }

        $this->dispatch('task-full-editor-updated', taskId: $this->getOwnerRecord()->getKey());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('file_path')
                    ->label('Plik')
                    ->required()
                    ->disk('public')
                    ->visibility('public')
                    ->directory('task-attachments')
                    ->storeFileNamesIn('name')
                    ->preserveFilenames()
                    ->openable()
                    ->downloadable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->panelMode ? static::$title : null)
            ->paginated($this->panelMode ? false : true)
            ->searchable(! $this->panelMode)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Plik')
                    ->searchable(! $this->panelMode)
                    ->wrap()
                    ->color('primary')
                    ->url(fn ($record) => $record->preview_url)
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('readable_size')
                    ->label('Rozm.')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: ! $this->panelMode),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Dodane przez')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data dodania')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->when($this->panelMode, fn (Tables\Actions\CreateAction $action) => $action
                        ->label('Dodaj plik')
                        ->icon('heroicon-o-plus')
                        ->iconButton())
                    ->modalHeading('Dodaj załącznik')
                    ->mutateFormDataUsing(function (array $data): array {
                        $disk = Storage::disk('public');
                        $path = $data['file_path'] ?? null;

                        $data['user_id'] = Auth::id();
                        $data['mime_type'] = $path && $disk->exists($path) ? $disk->mimeType($path) : null;
                        $data['size'] = $path && $disk->exists($path) ? $disk->size($path) : null;
                        $data['name'] = $data['name'] ?? basename((string) $path);

                        return $data;
                    })
                    ->after(fn () => $this->dispatchPanelUpdated()),
            ])->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Podgląd')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => $record->preview_url)
                    ->openUrlInNewTab()
                    ->when($this->panelMode, fn (Tables\Actions\Action $action) => $action->iconButton()),
                Tables\Actions\Action::make('download')
                    ->label('Pobierz')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record) => $record->download_url)
                    ->openUrlInNewTab()
                    ->when($this->panelMode, fn (Tables\Actions\Action $action) => $action->iconButton()),
                Tables\Actions\DeleteAction::make()
                    ->when($this->panelMode, fn (Tables\Actions\DeleteAction $action) => $action->iconButton())
                    ->after(fn () => $this->dispatchPanelUpdated()),
            ])
            ->bulkActions($this->panelMode ? [] : [
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
