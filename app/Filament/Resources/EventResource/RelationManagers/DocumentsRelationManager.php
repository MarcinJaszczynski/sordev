<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Filament\Resources\TaskResource;
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

    protected static ?string $title = 'Dokumenty';

    protected static ?string $label = 'dokument';

    protected static ?string $pluralLabel = 'dokumenty';

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

                        Forms\Components\Toggle::make('is_offer')
                            ->label('To jest oferta dla klienta')
                            ->inline(false)
                            ->live()
                            ->default(false),

                        Forms\Components\Toggle::make('is_invoice')
                            ->label('To jest faktura')
                            ->inline(false)
                            ->default(false),

                        Forms\Components\Select::make('offer_status')
                            ->label('Status oferty')
                            ->options(EventDocument::$offerStatuses)
                            ->default('draft')
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_offer')),

                        Forms\Components\DateTimePicker::make('offer_sent_at')
                            ->label('Wysłano ofertę')
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_offer')),

                        Forms\Components\DateTimePicker::make('offer_response_at')
                            ->label('Data odpowiedzi klienta')
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_offer')),

                        \FilamentTiptapEditor\TiptapEditor::make('offer_response_notes')
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_offer'))
                            ->columnSpanFull(),

                        \FilamentTiptapEditor\TiptapEditor::make('offer_modification_notes')
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_offer'))
                            ->columnSpanFull(),

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
                    ->description('Zaznacz, do których pakietów PDF dokument ma być automatycznie dołączany.')
                    ->schema([
                        Forms\Components\CheckboxList::make('pdf_attachment_targets')
                            ->label('Pakiety PDF')
                            ->options([
                                'attach_to_pilot_pdf' => '✈ Pakiet pilota',
                                'attach_to_hotel_pdf' => '🏨 Pakiet hotelu',
                                'attach_to_driver_pdf' => '🚌 Pakiet kierowcy',
                                'attach_to_folder_pdf' => '📁 Pakiet teczki',
                            ])
                            ->columns(2)
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

                        return $cost ? '💰 '.$cost->name : '—';
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

                Tables\Columns\IconColumn::make('is_offer')
                    ->label('Oferta')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('is_invoice')
                    ->label('Faktura')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\BadgeColumn::make('offer_status')
                    ->label('Status oferty')
                    ->formatStateUsing(fn (?string $state) => EventDocument::$offerStatuses[$state ?? 'draft'] ?? $state)
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'sent',
                        'warning' => 'changes_requested',
                        'success' => 'accepted',
                        'danger' => 'rejected',
                        'primary' => 'responded',
                    ])
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('offer_sent_at')
                    ->label('Wysłano')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('offer_response_at')
                    ->label('Odpowiedź')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false),

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

                Tables\Columns\TextColumn::make('review_notes')
                    ->label('Uwagi kontrolne')
                    ->html(false)
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dodano')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Zweryfikowano')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
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

                Tables\Filters\Filter::make('is_offer')
                    ->label('Tylko oferty')
                    ->query(fn ($query) => $query->where('is_offer', true)),

                Tables\Filters\Filter::make('is_invoice')
                    ->label('Tylko faktury')
                    ->query(fn ($query) => $query->where('is_invoice', true)),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Dodaj dokument')
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

                Tables\Actions\Action::make('mark_offer_sent')
                    ->label('Oznacz jako wysłaną')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (EventDocument $record) => (bool) $record->is_offer && blank($record->offer_sent_at))
                    ->action(function (EventDocument $record): void {
                        $record->update([
                            'offer_status' => 'sent',
                            'offer_sent_at' => now(),
                        ]);
                    }),

                Tables\Actions\Action::make('register_offer_response')
                    ->label('Zarejestruj odpowiedź')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('warning')
                    ->visible(fn (EventDocument $record) => (bool) $record->is_offer)
                    ->form([
                        Forms\Components\Select::make('offer_status')
                            ->label('Status po odpowiedzi')
                            ->options(EventDocument::$offerStatuses)
                            ->required()
                            ->default(fn (EventDocument $record) => $record->offer_status ?: 'responded'),
                        \FilamentTiptapEditor\TiptapEditor::make('offer_response_notes')
                            ->required(),
                        Forms\Components\DateTimePicker::make('offer_response_at')
                            ->label('Data odpowiedzi')
                            ->default(now()),
                    ])
                    ->action(function (EventDocument $record, array $data): void {
                        $record->update([
                            'offer_status' => $data['offer_status'] ?? 'responded',
                            'offer_response_notes' => $data['offer_response_notes'] ?? null,
                            'offer_response_at' => $data['offer_response_at'] ?? now(),
                        ]);
                    }),

                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(fn (array $data) => $this->mutateDataWithFlags($data)),

                Tables\Actions\Action::make('create_task')
                    ->label('Dodaj zadanie')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('primary')
                    ->url(fn (EventDocument $record): string => TaskResource::getUrl('create', [
                        'taskable_type' => EventDocument::class,
                        'taskable_id' => $record->getKey(),
                    ]))
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

        $data['is_offer'] = (bool) ($data['is_offer'] ?? false);
        $data['is_invoice'] = (bool) ($data['is_invoice'] ?? false);

        if ($data['is_offer']) {
            $data['offer_status'] = $data['offer_status'] ?? 'draft';
        } else {
            $data['offer_status'] = 'draft';
            $data['offer_sent_at'] = null;
            $data['offer_response_at'] = null;
            $data['offer_response_notes'] = null;
            $data['offer_modification_notes'] = null;
        }

        unset($data['pdf_attachment_targets']);

        return $data;
    }
}
