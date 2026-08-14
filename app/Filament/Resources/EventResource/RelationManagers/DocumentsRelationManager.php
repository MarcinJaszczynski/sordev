<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\EventDocument;
use App\Models\EventSettlementCost;
use App\Support\StoragePath;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Załączniki';

    protected static ?string $label = 'załącznik';

    protected static ?string $pluralLabel = 'załączniki';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informacje o dokumencie')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa dokumentu')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_invoice')
                            ->label('To jest faktura')
                            ->inline(false)
                            ->default(false),

                        \FilamentTiptapEditor\TiptapEditor::make('notes')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Plik')
                    ->schema([
                        Forms\Components\FileUpload::make('file_path')
                            ->label('Plik')
                            ->disk('public')
                            ->directory('event-documents')
                            ->preserveFilenames(false)
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/*',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/plain',
                            ])
                            ->maxSize(20480)
                            ->storeFileNamesIn('original_filename')
                            ->columnSpanFull()
                            ->afterStateUpdated(function ($set, $state) {
                                // capture mime type and file size when file is uploaded
                            }),
                    ]),

                Forms\Components\Section::make('Dołącz do pakietów PDF')
                    ->description('Zaznacz „Pakiet pilota”, aby plik był widoczny w panelu pilota i w pakiecie PDF. Kontrola (akceptacja) nie blokuje widoczności — blokuje tylko status „Odrzucony”.')
                    ->schema([
                        Forms\Components\CheckboxList::make('pdf_attachment_targets')
                            ->label('Pakiety PDF')
                            ->options([
                                'attach_to_pilot_pdf' => 'Pakiet pilota (panel + PDF)',
                                'attach_to_hotel_pdf' => 'Pakiet hotelu',
                                'attach_to_driver_pdf' => 'Pakiet kierowcy',
                                'attach_to_folder_pdf' => 'Pakiet teczki',
                            ])
                            ->columns(['default' => 1, 'md' => 2])
                            ->afterStateHydrated(function ($state, $record, $set) {
                                if (! $record) {
                                    return;
                                }
                                $targets = [];
                                foreach (EventDocument::$pdfTargetLabels as $column => $_) {
                                    if ($record->$column) {
                                        $targets[] = $column;
                                    }
                                }
                                $set('pdf_attachment_targets', $targets);
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('is_offer', false))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('file_info')
                    ->label('Plik')
                    ->state(function (EventDocument $record): string {
                        if (! $record->file_path) {
                            return '—';
                        }
                        $name = $record->original_filename ?? basename($record->file_path);
                        $size = $record->file_size_formatted;

                        return $name.($size ? " ({$size})" : '');
                    })
                    ->limit(40),

                Tables\Columns\TextColumn::make('settlement_cost_source')
                    ->label('Źródło / pozycja')
                    ->state(function (EventDocument $record): string {
                        if (! $record->settlement_cost_id) {
                            return '—';
                        }

                        $cost = EventSettlementCost::find($record->settlement_cost_id);

                        return $cost ? $cost->name : '—';
                    })
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\BadgeColumn::make('approval_status')
                    ->label('Kontrola')
                    ->formatStateUsing(fn (?string $state) => EventDocument::$approvalStatuses[$state ?? 'pending'] ?? $state)
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),

                Tables\Columns\IconColumn::make('is_invoice')
                    ->label('Faktura')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('attach_to_pilot_pdf')
                    ->label('Pilot')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('attach_to_hotel_pdf')
                    ->label('Hotel')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('attach_to_driver_pdf')
                    ->label('Kierowca')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('attach_to_folder_pdf')
                    ->label('Teczka')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Uwagi')
                    ->html(false)
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dodano')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('attach_to_pilot_pdf')
                    ->label('Pakiet pilota')
                    ->query(fn ($query) => $query->where('attach_to_pilot_pdf', true)),

                Tables\Filters\Filter::make('attach_to_hotel_pdf')
                    ->label('Pakiet hotelu')
                    ->query(fn ($query) => $query->where('attach_to_hotel_pdf', true)),

                Tables\Filters\Filter::make('attach_to_driver_pdf')
                    ->label('Pakiet kierowcy')
                    ->query(fn ($query) => $query->where('attach_to_driver_pdf', true)),

                Tables\Filters\Filter::make('attach_to_folder_pdf')
                    ->label('Pakiet teczki')
                    ->query(fn ($query) => $query->where('attach_to_folder_pdf', true)),

                Tables\Filters\Filter::make('is_invoice')
                    ->label('Tylko faktury')
                    ->query(fn ($query) => $query->where('is_invoice', true)),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Dodaj załącznik')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateDataWithFlags($data)),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Pobierz')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (EventDocument $record) => (bool) $record->file_path)
                    ->url(fn (EventDocument $record) => StoragePath::publicUrl($record->file_path))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateDataWithFlags($data)),

                Tables\Actions\Action::make('create_task')
                    ->label('Dodaj zadanie')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('primary')
                    ->url(fn (EventDocument $record): string => \App\Support\Tasks\TaskNavigation::createUrl(
                        \App\Models\EventDocument::class,
                        $record->getKey(),
                    ))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('approve')
                    ->label('Akceptuj')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (EventDocument $record): void {
                        $record->update([
                            'approval_status' => 'approved',
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                        ]);
                    })
                    ->visible(fn (EventDocument $record) => $record->approval_status !== 'approved'),

                Tables\Actions\Action::make('reject')
                    ->label('Odrzuć')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        \FilamentTiptapEditor\TiptapEditor::make('review_notes')
                            ->required(),
                    ])
                    ->action(function (EventDocument $record, array $data): void {
                        $record->update([
                            'approval_status' => 'rejected',
                            'review_notes' => $data['review_notes'] ?? null,
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                        ]);
                    })
                    ->visible(fn (EventDocument $record) => $record->approval_status !== 'rejected'),

                Tables\Actions\Action::make('reset_approval')
                    ->label('Cofnij akceptację')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (EventDocument $record): void {
                        $record->update([
                            'approval_status' => 'pending',
                            'review_notes' => null,
                            'reviewed_by' => null,
                            'reviewed_at' => null,
                        ]);
                    })
                    ->visible(fn (EventDocument $record) => $record->approval_status !== 'pending'),

                Tables\Actions\DeleteAction::make()
                    ->after(function (EventDocument $record) {
                        $normalizedPath = StoragePath::normalize($record->file_path);

                        if ($normalizedPath) {
                            Storage::disk('public')->delete($normalizedPath);
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Mapuje checkboxy pdf_attachment_targets na 4 oddzielne kolumny boolean.
     */
    protected function mutateDataWithFlags(array $data): array
    {
        $targets = $data['pdf_attachment_targets'] ?? [];

        foreach (EventDocument::$pdfTargetLabels as $column => $_) {
            $data[$column] = in_array($column, (array) $targets, true);
        }

        $data['is_offer'] = false;
        $data['is_invoice'] = (bool) ($data['is_invoice'] ?? false);
        $data['offer_status'] = 'draft';
        $data['offer_sent_at'] = null;
        $data['offer_response_at'] = null;
        $data['offer_response_notes'] = null;
        $data['offer_modification_notes'] = null;

        unset($data['pdf_attachment_targets']);

        return $data;
    }
}
