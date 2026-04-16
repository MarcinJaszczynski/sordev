<?php

namespace App\Filament\Resources\EventSettlementResource\RelationManagers;

use App\Filament\Resources\TaskResource;
use App\Models\Currency;
use App\Models\EventSettlementDocument;
use App\Support\StoragePath;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';
    protected static ?string $title = 'Faktury i dokumenty';
    protected static ?string $recordTitleAttribute = 'document_number';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dokument')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('document_type')
                        ->label('Typ dokumentu')
                        ->options(EventSettlementDocument::$documentTypes)
                        ->default('invoice')
                        ->required(),

                    Forms\Components\TextInput::make('document_number')
                        ->label('Numer dokumentu')
                        ->maxLength(255)
                        ->nullable(),

                    Forms\Components\TextInput::make('vendor_name')
                        ->label('Kontrahent / wystawca')
                        ->maxLength(255)
                        ->nullable(),

                    Forms\Components\Select::make('payer_scope')
                        ->label('Płatność realizuje')
                        ->options(EventSettlementDocument::$payerScopes)
                        ->nullable(),

                    Forms\Components\Select::make('payment_method')
                        ->label('Forma płatności')
                        ->options(EventSettlementDocument::$paymentMethods)
                        ->nullable(),

                    Forms\Components\DateTimePicker::make('payment_date')
                        ->label('Data płatności')
                        ->nullable(),

                    Forms\Components\TextInput::make('total_amount')
                        ->label('Kwota dokumentu')
                        ->numeric()
                        ->nullable(),

                    Forms\Components\Select::make('currency_id')
                        ->label('Waluta dokumentu')
                        ->options(fn () => Currency::orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->nullable(),

                    Forms\Components\DatePicker::make('issue_date')
                        ->label('Data wystawienia')
                        ->nullable(),
                ]),

            Forms\Components\Section::make('Powiązane punkty/koszty')
                ->schema([
                    Forms\Components\Select::make('linked_cost_ids')
                        ->label('Pozycje na tej fakturze')
                        ->multiple()
                        ->searchable()
                        ->options(function () {
                            return $this->getOwnerRecord()
                                ->costs()
                                ->orderBy('order')
                                ->get()
                                ->mapWithKeys(fn ($cost) => [
                                    $cost->id => '#'.$cost->id.' '.$cost->name.' (plan: '.number_format((float) ($cost->planned_amount_pln ?? 0), 2).' PLN)',
                                ])
                                ->all();
                        })
                        ->helperText('Możesz wskazać wiele punktów programu na jednej fakturze.'),
                ]),

            Forms\Components\Section::make('Załączniki i uwagi')
                ->schema([
                    Forms\Components\FileUpload::make('files')
                        ->label('Faktura / zdjęcia faktury / inne pliki')
                        ->multiple()
                        ->directory('event-settlement-documents')
                        ->preserveFilenames()
                        ->downloadable()
                        ->openable(),

                    Forms\Components\RichEditor::make('notes')
                        ->columnSpanFull(),

                    Forms\Components\CheckboxList::make('pdf_attachment_targets')
                        ->label('Dołączaj do pakietów PDF')
                        ->options([
                            'pilot' => 'PDF pilota',
                            'hotel' => 'PDF hotelu',
                            'driver' => 'PDF kierowcy',
                            'folder' => 'PDF teczki imprezy',
                        ])
                        ->columns(2)
                        ->helperText('Wybierz, do których pakietów ten dokument może być dołączany.')
                        ->afterStateHydrated(function (Forms\Components\CheckboxList $component, $record) {
                            if (! $record) {
                                return;
                            }

                            $state = [];

                            if ($record->attach_to_pilot_pdf) {
                                $state[] = 'pilot';
                            }
                            if ($record->attach_to_hotel_pdf) {
                                $state[] = 'hotel';
                            }
                            if ($record->attach_to_driver_pdf) {
                                $state[] = 'driver';
                            }
                            if ($record->attach_to_folder_pdf) {
                                $state[] = 'folder';
                            }

                            $component->state($state);
                        }),
                ]),
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->mutateAttachmentTargetFlags($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mutateAttachmentTargetFlags($data);
    }

    private function mutateAttachmentTargetFlags(array $data): array
    {
        $targets = collect($data['pdf_attachment_targets'] ?? [])->filter()->values();

        $data['attach_to_pilot_pdf'] = $targets->contains('pilot');
        $data['attach_to_hotel_pdf'] = $targets->contains('hotel');
        $data['attach_to_driver_pdf'] = $targets->contains('driver');
        $data['attach_to_folder_pdf'] = $targets->contains('folder');

        unset($data['pdf_attachment_targets']);

        return $data;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('document_type')
                    ->label('Typ')
                    ->formatStateUsing(fn ($state) => EventSettlementDocument::$documentTypes[$state] ?? $state)
                    ->colors([
                        'primary' => 'invoice',
                        'info' => 'receipt',
                        'gray' => 'other',
                    ]),

                Tables\Columns\TextColumn::make('document_number')
                    ->label('Nr dokumentu')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('vendor_name')
                    ->label('Kontrahent')
                    ->searchable()
                    ->placeholder('—')
                    ->wrap(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Kwota')
                    ->numeric(2)
                    ->placeholder('—')
                    ->description(fn ($record) => $record->currency?->symbol ?? null),

                Tables\Columns\TextColumn::make('linked_costs_summary')
                    ->label('Powiązane punkty')
                    ->wrap()
                    ->limit(70),

                Tables\Columns\TextColumn::make('files_count')
                    ->label('Pliki')
                    ->state(fn ($record) => count($record->files ?? []))
                    ->alignCenter(),

                Tables\Columns\BadgeColumn::make('approval_status')
                    ->label('Kontrola')
                    ->formatStateUsing(fn (?string $state) => EventSettlementDocument::$approvalStatuses[$state ?? 'pending'] ?? $state)
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),

                Tables\Columns\ToggleColumn::make('attach_to_pilot_pdf')
                    ->label('Pilot PDF'),

                Tables\Columns\ToggleColumn::make('attach_to_hotel_pdf')
                    ->label('Hotel PDF'),

                Tables\Columns\ToggleColumn::make('attach_to_driver_pdf')
                    ->label('Kierowca PDF'),

                Tables\Columns\ToggleColumn::make('attach_to_folder_pdf')
                    ->label('Teczka PDF'),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Data płatności')
                    ->date('d.m.Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('review_notes')
                    ->label('Uwagi kontrolne')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Zweryfikowano')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('approval_pending')
                    ->label('Niezaakceptowane')
                    ->query(fn ($query) => $query->where('approval_status', 'pending')),

                Tables\Filters\Filter::make('approval_approved')
                    ->label('Zaakceptowane')
                    ->query(fn ($query) => $query->where('approval_status', 'approved')),

                Tables\Filters\Filter::make('approval_rejected')
                    ->label('Odrzucone')
                    ->query(fn ($query) => $query->where('approval_status', 'rejected')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Dodaj fakturę/dokument'),
            ])
            ->actions([
                Tables\Actions\Action::make('open_first_file')
                    ->label('Otwórz plik')
                    ->icon('heroicon-o-paper-clip')
                    ->url(function ($record) {
                        $first = collect($record->files ?? [])->first();

                        return StoragePath::publicUrl($first);
                    })
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => !empty($record->files)),

                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('create_task')
                    ->label('Dodaj zadanie')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('primary')
                    ->url(fn (EventSettlementDocument $record): string => TaskResource::getUrl('create', [
                        'taskable_type' => EventSettlementDocument::class,
                        'taskable_id' => $record->getKey(),
                    ]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('approve')
                    ->label('Akceptuj')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (EventSettlementDocument $record): void {
                        $record->update([
                            'approval_status' => 'approved',
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                        ]);
                    })
                    ->visible(fn (EventSettlementDocument $record) => $record->approval_status !== 'approved'),

                Tables\Actions\Action::make('reject')
                    ->label('Odrzuć')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\RichEditor::make('notes')
                            ->required(),
                    ])
                    ->action(function (EventSettlementDocument $record, array $data): void {
                        $record->update([
                            'approval_status' => 'rejected',
                            'review_notes' => $data['review_notes'] ?? null,
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                        ]);
                    })
                    ->visible(fn (EventSettlementDocument $record) => $record->approval_status !== 'rejected'),

                Tables\Actions\Action::make('reset_approval')
                    ->label('Cofnij akceptację')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (EventSettlementDocument $record): void {
                        $record->update([
                            'approval_status' => 'pending',
                            'review_notes' => null,
                            'reviewed_by' => null,
                            'reviewed_at' => null,
                        ]);
                    })
                    ->visible(fn (EventSettlementDocument $record) => $record->approval_status !== 'pending'),

                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }
}
