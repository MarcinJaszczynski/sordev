<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Filament\Forms\ContractAnnexFields;
use App\Filament\Forms\ContractCustomAgreementFields;
use App\Filament\Forms\ContractGroupPricingFields;
use App\Filament\Forms\ContractOrderingPartyFields;
use App\Filament\Forms\ContractParticipantFields;
use App\Filament\Forms\ContractTfgForm;
use App\Filament\Resources\ContractResource;
use App\Filament\Resources\EventResource\RelationManagers\Concerns\ManagesContractAttachments;
use App\Filament\Resources\EventResource\RelationManagers\Concerns\ManagesContractOrderingParties;
use App\Filament\Resources\EventResource\RelationManagers\Concerns\ManagesContractPaymentSchedules;
use App\Jobs\Tfg\SubmitTfgFeedJob;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Event;
use App\Models\EventSettlementParticipantPayment;
use App\Services\ContractAnnexService;
use App\Services\ContractOrderingPartyService;
use App\Services\ContractPaymentSyncService;
use App\Services\ContractTfgSetupService;
use App\Services\Contracts\ContractPaymentProgressService;
use App\Services\NotificationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;

class ContractsRelationManager extends RelationManager
{
    use ManagesContractAttachments;
    use ManagesContractOrderingParties;
    use ManagesContractPaymentSchedules;

    protected static string $relationship = 'agreements';

    protected static ?string $title = 'Umowy i płatności';

    protected static ?string $recordTitleAttribute = 'contract_number';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Podstawowe dane umowy')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('contract_template_id')
                        ->label('Szablon umowy')
                        ->options(fn () => ContractTemplate::orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->nullable()
                        ->live()
                        ->visible(fn (Get $get): bool => ($get('agreement_type') ?? Contract::TYPE_GROUP) !== Contract::TYPE_CUSTOM)
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $set('selected_attachments', $this->resolveSelectedAttachmentDefaults(
                                null,
                                filled($state) ? (int) $state : null,
                            ));
                        }),

                    Forms\Components\Select::make('agreement_type')
                        ->label('Typ umowy')
                        ->options(fn (): array => collect(Contract::$types)
                            ->except(Contract::TYPE_TEMPLATE)
                            ->all())
                        ->default(Contract::TYPE_GROUP)
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            if ($state === Contract::TYPE_CUSTOM) {
                                $set('body_edit_mode', Contract::BODY_EDIT_UPLOAD);
                                $set('contract_template_id', null);

                                return;
                            }

                            $set('body_edit_mode', Contract::BODY_EDIT_TEMPLATE);

                            $event = $this->getOwnerRecord();
                            $participantCount = max(1, (int) ($event->participant_count ?? 1));
                            $pricePerPerson = $event->resolvedPricePerPerson($participantCount);

                            if ($state === Contract::TYPE_INDIVIDUAL) {
                                $set('amount_due', $pricePerPerson);
                                $set('participant_count', 1);
                            } else {
                                $set('amount_due', $pricePerPerson * $participantCount);
                                $set('participant_count', $participantCount);
                                $set('unit_price', $pricePerPerson);
                            }
                        })
                        ->required(),

                    Forms\Components\Select::make('participant_payment_id')
                        ->label('Powiązany płatny uczestnik')
                        ->options(fn () => $this->getParticipantPaymentOptions())
                        ->searchable()
                        ->nullable()
                        ->helperText('Opcjonalnie: podepnij pozycję z rozliczenia. Bez rozliczenia kwota zostanie policzona jako koszt imprezy / liczba płatnych uczestników.'),

                    Forms\Components\TextInput::make('title')
                        ->label('Tytuł umowy')
                        ->default(fn () => 'Umowa — '.$this->getOwnerRecord()->name)
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('agreement_number')
                        ->label('Numer operacyjny')
                        ->maxLength(255)
                        ->helperText('Kod imprezy + U001 dla indywidualnych. Puste = nadanie automatyczne.'),

                    Forms\Components\DatePicker::make('agreement_date')
                        ->label('Data umowy')
                        ->native(false)
                        ->default(now()),
                ]),

            Forms\Components\Section::make('Dane imprezy')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('event_name')
                        ->label('Nazwa imprezy')
                        ->default(fn () => $this->getOwnerRecord()->name)
                        ->maxLength(255),

                    Forms\Components\TextInput::make('participant_count')
                        ->label('Liczba uczestników')
                        ->numeric()
                        ->minValue(1)
                        ->default(fn () => (int) ($this->getOwnerRecord()->participant_count ?? 1))
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, Get $get): void {
                            ContractTfgForm::syncMainFieldsToTfg($set, $get);
                            ContractGroupPricingFields::syncGroupTotal($set, $get, fn () => $this->getOwnerRecord());
                        }),

                    Forms\Components\DatePicker::make('event_start_date')
                        ->label('Data rozpoczęcia')
                        ->default(fn () => $this->getOwnerRecord()->start_date)
                        ->native(false)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, Get $get): void {
                            ContractTfgForm::syncMainFieldsToTfg($set, $get);
                            ContractGroupPricingFields::syncGroupTotal($set, $get, fn () => $this->getOwnerRecord());
                        }),

                    Forms\Components\DatePicker::make('event_end_date')
                        ->label('Data zakończenia')
                        ->default(fn () => $this->getOwnerRecord()->end_date)
                        ->native(false)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, Get $get): void {
                            ContractTfgForm::syncMainFieldsToTfg($set, $get);
                            ContractGroupPricingFields::syncGroupTotal($set, $get, fn () => $this->getOwnerRecord());
                        }),

                    Forms\Components\TextInput::make('participant_name')
                        ->label('Uczestnik (indywidualna)')
                        ->maxLength(255),

                    Forms\Components\DatePicker::make('participant_birth_date')
                        ->label('Data urodzenia uczestnika')
                        ->native(false),

                ]),

            Forms\Components\Section::make('Zamawiający')
                ->schema(ContractOrderingPartyFields::schema()),

            Forms\Components\Section::make('Płatność')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('amount_due')
                        ->label('Kwota do zapłaty')
                        ->numeric()
                        ->default(fn () => (function () {
                            $event = $this->getOwnerRecord();
                            $count = max(1, (int) ($event->participant_count ?? 1));

                            return $event->resolvedPricePerPerson($count) * $count;
                        })())
                        ->required()
                        ->suffix('PLN')
                        ->helperText(fn (Get $get) => (function () use ($get): string {
                            if (ContractGroupPricingFields::isGroupType($get('agreement_type'))) {
                                $participantCount = max(1, (int) ($get('participant_count') ?? 1));
                                $unitPrice = (float) ($get('unit_price') ?? 0);

                                if ($unitPrice <= 0) {
                                    $event = $this->getOwnerRecord();
                                    $unitPrice = $event->resolvedPricePerPerson($participantCount);
                                }

                                return sprintf(
                                    'Kalkulacja grupowa: %s PLN/os. × %d os. = %s PLN',
                                    number_format($unitPrice, 2, ',', ' '),
                                    $participantCount,
                                    number_format($unitPrice * $participantCount, 2, ',', ' ')
                                );
                            }

                            $event = $this->getOwnerRecord();
                            $participantCount = max(1, (int) ($event->participant_count ?? 1));
                            $pricePerPerson = $event->resolvedPricePerPerson($participantCount);
                            $totalAmount = $pricePerPerson * $participantCount;

                            return sprintf(
                                'Kalkulacja: %s PLN/os. × %d os. = %s PLN',
                                number_format($pricePerPerson, 2, ',', ' '),
                                $participantCount,
                                number_format($totalAmount, 2, ',', ' ')
                            );
                        })()),

                    ...ContractGroupPricingFields::schema(fn () => $this->getOwnerRecord()),

                    Forms\Components\TextInput::make('amount_paid')
                        ->label('Kwota opłacona')
                        ->numeric()
                        ->default(0)
                        ->suffix('PLN'),

                    Forms\Components\Select::make('currency')
                        ->label('Waluta')
                        ->options([
                            'PLN' => 'PLN',
                            'EUR' => 'EUR',
                            'USD' => 'USD',
                        ])
                        ->default('PLN')
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->label('Status umowy')
                        ->options(Contract::$statuses)
                        ->default('sent')
                        ->required(),

                    Forms\Components\Select::make('payment_status')
                        ->label('Status płatności')
                        ->options(Contract::$paymentStatuses)
                        ->default('pending')
                        ->required(),

                    Forms\Components\Select::make('payment_method')
                        ->label('Metoda płatności')
                        ->options(Contract::$paymentMethods)
                        ->nullable(),

                    Forms\Components\DateTimePicker::make('signed_at')
                        ->label('Data zawarcia')
                        ->nullable(),

                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label('Data płatności')
                        ->nullable(),
                ]),

            ...ContractParticipantFields::schema(true),

            ...ContractTfgForm::schema(true, fn () => $this->getOwnerRecord(), includeParticipantFields: false),

            Forms\Components\Section::make('Aneks')
                ->visible(fn (?Contract $record): bool => $record?->isAnnex() ?? false)
                ->schema(ContractAnnexFields::editSchema()),

            Forms\Components\Section::make('Treść i załączniki')
                ->schema([
                    Forms\Components\Group::make()
                        ->visible(fn (?Contract $record): bool => ! ($record?->isAnnex() ?? false))
                        ->schema(ContractCustomAgreementFields::contentSchema()),

                    Forms\Components\CheckboxList::make('selected_attachments')
                        ->label('Wybierz gotowe załączniki')
                        ->options(fn (): array => $this->getSelectableAttachmentOptions())
                        ->columns(1)
                        ->helperText('Domyślnie zaznaczane są załączniki globalne lub przypisane do wybranego szablonu. Możesz też dodać własne pliki poniżej.')
                        ->default(fn (?Contract $record, Get $get): array => $this->resolveSelectedAttachmentDefaults(
                            $record?->attachments,
                            filled($get('contract_template_id')) ? (int) $get('contract_template_id') : $record?->contract_template_id,
                        )),

                    Forms\Components\FileUpload::make('attachments')
                        ->label('Załączniki do umowy')
                        ->multiple()
                        ->disk('public')
                        ->directory('event-agreements')
                        ->preserveFilenames()
                        ->downloadable()
                        ->openable(),

                    \FilamentTiptapEditor\TiptapEditor::make('admin_notes')
                        ->label('Uwagi administratora')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(function (): string {
                $event = $this->getOwnerRecord();
                $report = $event->buildIndividualAgreementReport($event->agreements()->get());
                $summary = $report['summary'];
                $insuranceLabel = $event->insuranceChecklistLabel();

                return sprintf(
                    'Umowy i płatności • Indywidualne opłacone: %s • Pozostało: %s PLN • Ubezpieczenie: %s',
                    $summary['payment_progress_label'],
                    number_format((float) $summary['amount_remaining'], 2, ',', ' '),
                    $insuranceLabel
                );
            })
            ->columns([
                Tables\Columns\TextColumn::make('operational_number')
                    ->label('Numer')
                    ->searchable(['operational_number', 'contract_number'])
                    ->sortable()
                    ->copyable()
                    ->formatStateUsing(fn (Contract $record): ?string => $record->display_number),

                Tables\Columns\TextColumn::make('contract_number')
                    ->label('TFG')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('payment_profile')
                    ->label('Profil')
                    ->state(fn (Contract $record): string => app(ContractPaymentProgressService::class)->forContract($record)['profile_label'])
                    ->badge()
                    ->color(fn (Contract $record): string => match (true) {
                        $record->usesIndividualParticipantPayments() => 'warning',
                        $record->isIndividual() => 'primary',
                        $record->contract_type === Contract::TYPE_CUSTOM => 'gray',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('title')
                    ->label('Umowa')
                    ->searchable()
                    ->wrap()
                    ->description(fn (Contract $record) => collect([
                        $record->event_name ?: null,
                        ($record->meta['is_annex'] ?? false)
                            ? 'Aneks do #'.($record->meta['parent_contract_id'] ?? '?')
                            : null,
                    ])->filter()->implode(' • ') ?: '—'),

                Tables\Columns\BadgeColumn::make('agreement_type')
                    ->label('Typ')
                    ->formatStateUsing(fn (Contract $record) => $record->agreement_type_label)
                    ->colors([
                        'info' => Contract::TYPE_GROUP,
                        'primary' => Contract::TYPE_INDIVIDUAL,
                    ]),

                Tables\Columns\TextColumn::make('ordering_parties_label')
                    ->label('Zamawiający')
                    ->state(fn (Contract $record) => app(ContractOrderingPartyService::class)->formattedPartyNames($record))
                    ->description(fn (Contract $record) => $record->ordering_party_notes)
                    ->wrap()
                    ->searchable(['customer_name', 'customer_email']),

                Tables\Columns\TextColumn::make('payment_progress')
                    ->label('Postęp wpłat')
                    ->state(fn (Contract $record): string => app(ContractPaymentProgressService::class)->forContract($record)['summary_label'])
                    ->wrap(),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Do zapłaty')
                    ->money('PLN')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Płatność')
                    ->formatStateUsing(fn ($state) => Contract::$paymentStatuses[$state] ?? $state)
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => 'failed',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Umowa')
                    ->formatStateUsing(fn ($state) => Contract::$statuses[$state] ?? $state)
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'sent',
                        'warning' => 'signed',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('public_link')
                    ->label('Link klienta')
                    ->state(fn (Contract $record) => $record->public_link)
                    ->copyable()
                    ->copyMessage('Skopiowano link')
                    ->limit(32),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Opłacono')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('tfg_status')
                    ->label('TFG')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('pending_operation')
                    ->label('Operacja TFG')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\Action::make('edit_event_insurance')
                    ->label('Ubezpieczenie imprezy')
                    ->icon('heroicon-o-shield-check')
                    ->color(fn (): string => $this->getOwnerRecord()->hasInsuranceDataSaved() ? 'success' : 'danger')
                    ->visible(fn (): bool => Schema::hasColumn('events', 'insurance_policy_number'))
                    ->form([
                        Forms\Components\TextInput::make('insurance_policy_number')
                            ->label('Nr polisy')
                            ->maxLength(255),

                        Forms\Components\Select::make('insurance_status')
                            ->label('Status ubezpieczenia')
                            ->options(Event::getInsuranceStatusOptions())
                            ->default('pending')
                            ->required(),

                        Forms\Components\Select::make('insurance_payment_status')
                            ->label('Status płatności ubezpieczenia')
                            ->options(Event::getInsurancePaymentStatusOptions())
                            ->default('pending')
                            ->required(),

                        Forms\Components\TextInput::make('insurance_amount')
                            ->label('Kwota ubezpieczenia')
                            ->numeric()
                            ->suffix('PLN')
                            ->nullable(),

                        Forms\Components\DateTimePicker::make('insurance_paid_at')
                            ->label('Data płatności')
                            ->native(false)
                            ->nullable(),

                        Forms\Components\FileUpload::make('insurance_document_path')
                            ->label('Dokument ubezpieczenia do wgrania')
                            ->disk('public')
                            ->directory('event-insurance')
                            ->downloadable()
                            ->openable()
                            ->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg', 'image/webp'])
                            ->nullable(),

                        Forms\Components\Textarea::make('insurance_terms')
                            ->label('Warunki ubezpieczenia')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->fillForm(fn (): array => $this->resolveInsuranceFormDefaults())
                    ->action(function (array $data): void {
                        $event = $this->getOwnerRecord();
                        $event->updateInsuranceFromFormData($data);
                        $event->refresh();

                        if ($userId = auth()->id()) {
                            NotificationService::clearCacheForUser($userId);
                        }

                        Notification::make()
                            ->title('Zapisano dane ubezpieczenia')
                            ->body($event->insuranceSaveSummary())
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('individual_agreements_report')
                    ->label('Raport umów indywidualnych')
                    ->icon('heroicon-o-chart-bar-square')
                    ->color('gray')
                    ->modalHeading('Raport zawartych umów indywidualnych')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zamknij')
                    ->modalWidth('7xl')
                    ->modalContent(function () {
                        $event = $this->getOwnerRecord();
                        $report = $event->buildIndividualAgreementReport($event->agreements()->get());

                        return view('filament.event-resource.individual-agreement-report', [
                            'event' => $event,
                            'report' => $report,
                        ]);
                    }),

                Tables\Actions\Action::make('export_individual_agreements_csv')
                    ->label('Eksport CSV')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn () => route('admin.events.individual-agreements.export', [
                        'event' => $this->getOwnerRecord()->id,
                        'format' => 'csv',
                    ]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('export_individual_agreements_xlsx')
                    ->label('Eksport XLSX')
                    ->icon('heroicon-o-table-cells')
                    ->color('info')
                    ->url(fn () => route('admin.events.individual-agreements.export', [
                        'event' => $this->getOwnerRecord()->id,
                        'format' => 'xlsx',
                    ]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('generate_from_event')
                    ->label('Generuj umowę grupową')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->fillForm(fn (): array => array_merge(
                        [
                            'title' => 'Umowa imprezy',
                            'participant_count' => max(1, (int) ($this->getOwnerRecord()->participant_count ?? 1)),
                            'amount_due' => (function (): float {
                                $event = $this->getOwnerRecord();
                                $count = max(1, (int) ($event->participant_count ?? 1));

                                return $event->resolvedPricePerPerson($count) * $count;
                            })(),
                        ],
                        app(ContractTfgSetupService::class)->defaultsFromEvent($this->getOwnerRecord()),
                        $this->groupPricingService()->defaultsFromEvent($this->getOwnerRecord()),
                    ))
                    ->form([
                        Forms\Components\Select::make('contract_template_id')
                            ->label('Szablon umowy')
                            ->options(fn () => ContractTemplate::orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->nullable(),

                        Forms\Components\TextInput::make('title')
                            ->label('Tytuł umowy')
                            ->default('Umowa imprezy')
                            ->required(),

                        Forms\Components\TextInput::make('amount_due')
                            ->label('Kwota do zapłaty')
                            ->numeric()
                            ->default(fn () => (function () {
                                $event = $this->getOwnerRecord();
                                $count = max(1, (int) ($event->participant_count ?? 1));

                                return $event->resolvedPricePerPerson($count) * $count;
                            })())
                            ->required()
                            ->suffix('PLN')
                            ->helperText(fn () => (function () {
                                $event = $this->getOwnerRecord();
                                $count = max(1, (int) ($event->participant_count ?? 1));
                                $pricePerPerson = $event->resolvedPricePerPerson($count);

                                return sprintf(
                                    'Kalkulacja: %s PLN/os. × %d os.',
                                    number_format($pricePerPerson, 2, ',', ' '),
                                    $count
                                );
                            })()),

                        Forms\Components\TextInput::make('participant_count')
                            ->label('Liczba uczestników')
                            ->numeric()
                            ->default(fn () => (int) ($this->getOwnerRecord()->participant_count ?? 1))
                            ->minValue(1)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, Get $get) => ContractTfgForm::syncMainFieldsToTfg($set, $get)),

                        Forms\Components\CheckboxList::make('selected_attachments')
                            ->label('Załączniki do dołączenia')
                            ->options(fn (): array => $this->getSelectableAttachmentOptions())
                            ->columns(1)
                            ->default(fn (Get $get): array => $this->resolveSelectedAttachmentDefaults(
                                null,
                                filled($get('contract_template_id')) ? (int) $get('contract_template_id') : null,
                            ))
                            ->helperText('Zaznaczone pliki z systemu lub katalogu pliki będą dostępne klientowi razem z umową.'),
                        ...ContractTfgForm::schema(false, fn () => $this->getOwnerRecord()),
                    ])
                    ->action(function (array $data): void {
                        $event = $this->getOwnerRecord();
                        $data = $this->attachmentCatalogService()->mergeSelectedAttachmentsIntoFormData($data);
                        $attachments = $data['attachments'] ?? [];

                        $agreement = Contract::create([
                            'event_id' => $event->id,
                            'contract_template_id' => $data['contract_template_id'] ?? null,
                            'agreement_type' => Contract::TYPE_GROUP,
                            'title' => $data['title'],
                            'agreement_date' => now()->toDateString(),
                            'event_name' => $event->name,
                            'event_start_date' => $event->start_date,
                            'event_end_date' => $event->end_date,
                            'customer_name' => $event->client_name,
                            'customer_email' => $event->client_email,
                            'customer_phone' => $event->client_phone,
                            'participant_count' => (int) ($data['participant_count'] ?? $event->participant_count ?? 1),
                            'amount_due' => (float) ($data['amount_due'] ?? 0),
                            'currency' => 'PLN',
                            'status' => 'sent',
                            'payment_status' => 'pending',
                            'attachments' => $attachments,
                            'created_by' => auth()->id(),
                            'subject_code' => $data['subject_code'] ?? null,
                            'payment_method_code' => $data['payment_method_code'] ?? null,
                            'reservation_number' => $data['reservation_number'] ?? null,
                        ]);

                        app(ContractTfgSetupService::class)->applyToContract($agreement, array_merge(
                            app(ContractTfgSetupService::class)->defaultsFromEvent($event),
                            $this->mergeGroupPricingIntoFormData($data),
                        ));

                        $this->syncOrderingPartiesForContract(
                            $agreement,
                            app(ContractOrderingPartyService::class)->partiesFromEvent($event),
                        );

                        $this->syncPaymentSchedulesForContract($agreement->fresh(), $data);

                        $agreement->regenerateAgreementBody();

                        Notification::make()
                            ->title('Umowa wygenerowana')
                            ->body('Utworzono umowę i link do zawarcia dla klienta.')
                            ->success()
                            ->actions([
                                NotificationAction::make('open')
                                    ->label('Otwórz link klienta')
                                    ->url($agreement->public_link)
                                    ->openUrlInNewTab(),
                            ])
                            ->send();
                    }),

                Tables\Actions\Action::make('generate_individual_from_settlement')
                    ->label('Generuj umowy indywidualne')
                    ->icon('heroicon-o-users')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('contract_template_id')
                            ->label('Szablon umowy')
                            ->options(fn () => ContractTemplate::orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->nullable(),

                        Forms\Components\TextInput::make('title')
                            ->label('Tytuł umowy')
                            ->default('Umowa uczestnika')
                            ->required(),

                        Forms\Components\TextInput::make('paying_participants_count')
                            ->label('Liczba płatnych uczestników')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->default(fn () => max(1, (int) ($this->getOwnerRecord()->participant_count ?? 1)))
                            ->helperText('Dla każdego płatnego uczestnika zostanie wygenerowany osobny link do umowy.'),

                        Forms\Components\CheckboxList::make('selected_attachments')
                            ->label('Załączniki do dołączenia')
                            ->options(fn (): array => $this->getSelectableAttachmentOptions())
                            ->columns(1)
                            ->default(fn (Get $get): array => $this->resolveSelectedAttachmentDefaults(
                                null,
                                filled($get('contract_template_id')) ? (int) $get('contract_template_id') : null,
                            ))
                            ->helperText('Zaznaczone pliki z systemu lub katalogu pliki trafią do szablonu umowy indywidualnej i do każdego klonu.'),
                        ...ContractTfgForm::schema(false, fn () => $this->getOwnerRecord()),
                    ])
                    ->action(function (array $data): void {
                        $event = $this->getOwnerRecord();
                        $payingParticipantsCount = max(1, (int) ($data['paying_participants_count'] ?? $event->participant_count ?? 1));
                        $amountPerPerson = $this->resolveIndividualAmountDueForEvent($payingParticipantsCount);
                        $data = $this->attachmentCatalogService()->mergeSelectedAttachmentsIntoFormData($data);
                        $attachments = $data['attachments'] ?? [];

                        if ($amountPerPerson <= 0) {
                            Notification::make()
                                ->title('Brak kosztu imprezy')
                                ->body('Aby policzyć cenę za osobę, ustaw koszt imprezy większy od 0.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $existingTemplate = (int) $event->agreements()
                            ->where('agreement_type', Contract::TYPE_INDIVIDUAL)
                            ->where('status', 'template')
                            ->count();

                        if ($existingTemplate > 0) {
                            Notification::make()
                                ->title('Szablon umowy indywidualnej już istnieje')
                                ->body('Już wygenerowałeś szablon. Wysyłaj jego link do uczestników.')
                                ->warning()
                                ->send();

                            return;
                        }

                        // Utwórz JEDEN szablon dla wszystkich N uczestników
                        $agreement = Contract::create([
                            'event_id' => $event->id,
                            'contract_template_id' => $data['contract_template_id'] ?? null,
                            'agreement_type' => Contract::TYPE_INDIVIDUAL,
                            'title' => ($data['title'] ?? 'Umowa uczestnika').' (szablon)',
                            'agreement_date' => now()->toDateString(),
                            'event_name' => $event->name,
                            'event_start_date' => $event->start_date,
                            'event_end_date' => $event->end_date,
                            'customer_name' => $event->client_name,
                            'customer_email' => $event->client_email,
                            'customer_phone' => $event->client_phone,
                            'participant_count' => 1,
                            'amount_due' => $amountPerPerson,
                            'currency' => 'PLN',
                            'status' => 'template',
                            'payment_status' => 'pending',
                            'attachments' => $attachments,
                            'created_by' => auth()->id(),
                            'meta' => ['is_individual_template' => true, 'expected_participants' => $payingParticipantsCount],
                            'subject_code' => $data['subject_code'] ?? null,
                            'payment_method_code' => $data['payment_method_code'] ?? null,
                            'reservation_number' => $data['reservation_number'] ?? null,
                        ]);

                        app(ContractTfgSetupService::class)->applyToContract($agreement, array_merge(
                            app(ContractTfgSetupService::class)->defaultsFromEvent($event),
                            $data,
                            ['tfg_travelers_count' => 1],
                        ));

                        $this->syncOrderingPartiesForContract(
                            $agreement,
                            app(ContractOrderingPartyService::class)->partiesFromEvent($event),
                        );

                        $agreement->regenerateAgreementBody();

                        Notification::make()
                            ->title('Szablon umowy indywidualnej wygenerowany')
                            ->body("Wysyłaj poniższy link do {$payingParticipantsCount} uczestników. Każdy wypełni formularz i zapłaci indywidualnie. Cena za osobę: ".number_format($amountPerPerson, 2, ',', ' ').' PLN.')
                            ->success()
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('open')
                                    ->label('Otwórz link')
                                    ->url($agreement->public_link)
                                    ->openUrlInNewTab(),
                                \Filament\Notifications\Actions\Action::make('copy')
                                    ->label('Skopiuj link')
                                    ->close(),
                            ])
                            ->send();
                    }),

                Tables\Actions\CreateAction::make()
                    ->label('Nowa umowa')
                    ->fillForm(fn (): array => $this->resolveContractDefaultsFromEvent())
                    ->mutateFormDataUsing(function (array $data): array {
                        $event = $this->getOwnerRecord();

                        $data = $this->mergeSelectedAttachmentsIntoData($data);
                        $data = $this->mergeOrderingPartiesIntoFormData($data);

                        $data['event_id'] = $event->id;
                        $data['created_by'] = auth()->id();

                        $data['event_name'] = $data['event_name'] ?? $event->name;
                        $data['event_start_date'] = $data['event_start_date'] ?? optional($event->start_date)?->toDateString();
                        $data['event_end_date'] = $data['event_end_date'] ?? optional($event->end_date)?->toDateString();
                        $data['customer_name'] = $data['customer_name'] ?? $event->client_name;
                        $data['customer_email'] = $data['customer_email'] ?? $event->client_email;
                        $data['customer_phone'] = $data['customer_phone'] ?? $event->client_phone;

                        $data['agreement_type'] = $data['agreement_type'] ?? Contract::TYPE_GROUP;

                        if (($data['agreement_type'] ?? null) === Contract::TYPE_INDIVIDUAL) {
                            $data['participant_count'] = 1;
                        }

                        if (! empty($data['participant_payment_id'])) {
                            $participantPayment = EventSettlementParticipantPayment::query()->find($data['participant_payment_id']);

                            if ($participantPayment) {
                                $data['participant_name'] = $data['participant_name'] ?? $participantPayment->participant_name;

                                if (($data['agreement_type'] ?? null) === Contract::TYPE_INDIVIDUAL && (! isset($data['amount_due']) || (float) $data['amount_due'] <= 0)) {
                                    $data['amount_due'] = (float) $participantPayment->due_amount_pln;
                                }
                            }
                        }

                        if (($data['agreement_type'] ?? null) === Contract::TYPE_INDIVIDUAL && (! isset($data['amount_due']) || (float) $data['amount_due'] <= 0)) {
                            $data['amount_due'] = $this->resolveIndividualAmountDueForEvent(
                                max(1, (int) ($event->participant_count ?? 1))
                            );
                        }

                        $data['status'] = $data['status'] ?? 'sent';
                        $data['payment_status'] = $data['payment_status'] ?? 'pending';

                        return $this->mergeContractMetaFromFormData(
                            $this->mergeGroupPricingIntoFormData($data)
                        );
                    })
                    ->after(function (Contract $record, array $data): void {
                        $this->syncOrderingPartiesForContract(
                            $record,
                            $data['ordering_parties'] ?? [],
                            $data['ordering_party_notes'] ?? null,
                        );

                        app(ContractTfgSetupService::class)->applyToContract(
                            $record,
                            array_merge(
                                app(ContractTfgSetupService::class)->defaultsFromEvent($this->getOwnerRecord()),
                                $data,
                            ),
                        );

                        $this->syncPaymentSchedulesForContract($record->fresh(), $data);

                        if (blank($record->agreement_body) && $record->shouldAutoGenerateAgreementBody()) {
                            $record->regenerateAgreementBody();
                        }

                        Notification::make()
                            ->title('Umowa utworzona')
                            ->body('Możesz już wysłać klientowi link do zawarcia umowy.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('open_public')
                    ->label('Otwórz link')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Contract $record) => $record->public_link)
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('download_pdf')
                    ->label('Pobierz PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (Contract $record) => route('admin.contracts.agreement-pdf', ['contract' => $record]))
                    ->openUrlInNewTab()
                    ->visible(fn (Contract $record): bool => filled($record->agreement_body) || $record->usesUploadedAgreementDocument()),

                Tables\Actions\Action::make('regenerate')
                    ->label('Regeneruj treść')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (Contract $record): bool => $record->shouldAutoGenerateAgreementBody())
                    ->action(function (Contract $record): void {
                        $record->regenerateAgreementBody();

                        Notification::make()
                            ->title('Treść umowy zaktualizowana')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('sync_group_participant_payments')
                    ->label('Synchronizuj wpłaty uczestników')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (Contract $record): bool => $record->usesIndividualParticipantPayments())
                    ->action(function (Contract $record): void {
                        app(ContractPaymentSyncService::class)->sync($record->fresh());

                        Notification::make()
                            ->title('Wpłaty uczestników zsynchronizowane')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('mark_paid')
                    ->label('Oznacz jako opłacona')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Contract $record) => $record->payment_status !== 'paid')
                    ->action(function (Contract $record): void {
                        $record->update([
                            'status' => 'completed',
                            'payment_status' => 'paid',
                            'amount_paid' => (float) $record->amount_due,
                            'paid_at' => now(),
                        ]);

                        app(ContractPaymentSyncService::class)->sync($record->fresh());

                        Notification::make()
                            ->title('Umowa oznaczona jako opłacona')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('create_annex')
                    ->label('Utwórz aneks')
                    ->icon('heroicon-o-document-plus')
                    ->color('info')
                    ->visible(fn (Contract $record) => ! ($record->meta['is_annex'] ?? false) && $record->status !== 'template')
                    ->form([
                        Forms\Components\TextInput::make('title')
                            ->label('Tytuł aneksu')
                            ->required()
                            ->default(fn (Contract $record) => sprintf(
                                'Aneks do umowy %s',
                                $record->contract_number ?: ('#'.$record->id),
                            )),

                        Forms\Components\DatePicker::make('agreement_date')
                            ->label('Data aneksu')
                            ->default(now())
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('amount_due')
                            ->label('Kwota aneksu')
                            ->numeric()
                            ->default(fn (Contract $record) => (float) $record->amount_due)
                            ->required()
                            ->suffix('PLN'),

                        Forms\Components\TextInput::make('participant_count')
                            ->label('Liczba uczestników')
                            ->numeric()
                            ->minValue(1)
                            ->default(fn (Contract $record) => (int) ($record->participant_count ?? 1)),

                        Forms\Components\DatePicker::make('event_start_date')
                            ->label('Data rozpoczęcia')
                            ->default(fn (Contract $record) => $record->event_start_date)
                            ->native(false),

                        Forms\Components\DatePicker::make('event_end_date')
                            ->label('Data zakończenia')
                            ->default(fn (Contract $record) => $record->event_end_date)
                            ->native(false),

                        ...ContractAnnexFields::createSchema(),

                        ...ContractTfgForm::schema(false, fn () => $this->getOwnerRecord()),
                    ])
                    ->fillForm(fn (Contract $record): array => array_merge(
                        app(ContractTfgSetupService::class)->defaultsFromContract($record),
                        [
                            'body_edit_mode' => $record->body_edit_mode ?? Contract::BODY_EDIT_TEMPLATE,
                            'annex_change_types' => $record->annex_change_types ?? [],
                            'annex_program_change_notes' => $record->annex_program_change_notes,
                        ],
                    ))
                    ->action(function (Contract $record, array $data): void {
                        $annex = app(ContractTfgSetupService::class)->createAnnex($record, $data);

                        Notification::make()
                            ->title('Aneks utworzony')
                            ->body('Możesz wysłać klientowi nowy link do aneksu.')
                            ->success()
                            ->actions([
                                NotificationAction::make('open')
                                    ->label('Otwórz link aneksu')
                                    ->url($annex->public_link)
                                    ->openUrlInNewTab(),
                            ])
                            ->send();
                    }),

                Tables\Actions\Action::make('edit_ufg_full')
                    ->label('Edycja UFG')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->url(fn (Contract $record) => ContractResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('cancel_contract')
                    ->label('Anuluj umowę')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Contract $record) => ! in_array($record->status, ['cancelled', 'template'], true))
                    ->action(function (Contract $record): void {
                        Contract::withoutEvents(function () use ($record): void {
                            $record->update(['status' => 'cancelled']);
                        });

                        app(ContractPaymentSyncService::class)->remove($record->fresh());

                        Notification::make()
                            ->title('Umowa anulowana')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reopen_contract')
                    ->label('Przywróć umowę')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Contract $record) => $record->status === 'cancelled')
                    ->action(function (Contract $record): void {
                        $record->update(['status' => 'sent']);

                        Notification::make()
                            ->title('Umowa przywrócona')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('submit_tfg')
                    ->label('Wyślij do TFG')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->visible(fn (Contract $record) => $record->canSubmitNewData() && blank($record->pending_operation))
                    ->requiresConfirmation()
                    ->action(function (Contract $record): void {
                        $record->queueTfgOperation(Contract::OP_NOWEDANE);
                        SubmitTfgFeedJob::dispatch([$record->id]);

                        Notification::make()
                            ->title('Umowa w kolejce do TFG')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('correct_tfg')
                    ->label('Koryguj w TFG')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (Contract $record) => $record->canCorrect())
                    ->form([
                        Forms\Components\Select::make('correction_reason')
                            ->label('Powód korekty')
                            ->options(config('tfg.correction_reasons'))
                            ->required(),
                    ])
                    ->action(function (Contract $record, array $data): void {
                        $record->queueTfgOperation(Contract::OP_KOREKTA, $data['correction_reason']);
                        SubmitTfgFeedJob::dispatch([$record->id]);

                        Notification::make()
                            ->title('Korekta w kolejce do TFG')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('terminate_tfg')
                    ->label('Rozwiąż w TFG')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->visible(fn (Contract $record) => $record->canTerminateOrDelete())
                    ->requiresConfirmation()
                    ->action(function (Contract $record): void {
                        $record->queueTfgOperation(Contract::OP_ROZWIAZANIE);
                        SubmitTfgFeedJob::dispatch([$record->id]);

                        Notification::make()
                            ->title('Rozwiązanie w kolejce do TFG')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('delete_tfg')
                    ->label('Usuń w TFG')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (Contract $record) => $record->canTerminateOrDelete())
                    ->requiresConfirmation()
                    ->action(function (Contract $record): void {
                        $record->queueTfgOperation(Contract::OP_USUNIECIE);
                        SubmitTfgFeedJob::dispatch([$record->id]);

                        Notification::make()
                            ->title('Usunięcie w kolejce do TFG')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->fillForm(fn (Contract $record): array => array_merge(
                        $record->toArray(),
                        app(ContractTfgSetupService::class)->defaultsFromContract($record),
                        [
                            'ordering_parties' => app(ContractOrderingPartyService::class)->partiesToFormState($record),
                            'payment_schedules' => $this->groupPricingService()->schedulesToFormState($record),
                            'payment_mode' => data_get($record->meta, 'payment_mode', Contract::CUSTOM_PAYMENT_TOTAL_LUMP),
                        ],
                    ))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data = $this->mergeSelectedAttachmentsIntoData($data);

                        return $this->mergeContractMetaFromFormData(
                            $this->mergeOrderingPartiesIntoFormData($data)
                        );
                    })
                    ->after(function (Contract $record, array $data): void {
                        $this->syncOrderingPartiesForContract(
                            $record,
                            $data['ordering_parties'] ?? [],
                            $data['ordering_party_notes'] ?? null,
                        );

                        app(ContractTfgSetupService::class)->applyToContract($record, $data);

                        $this->syncPaymentSchedulesForContract($record->fresh(), $data);

                        if ($record->isAnnex()) {
                            $annex = $record->fresh();
                            app(ContractAnnexService::class)->applyAnnexAttributes(
                                $annex,
                                $data,
                                $this->getOwnerRecord(),
                            );

                            $annex = $annex->fresh();

                            if ($annex->shouldAutoGenerateAgreementBody()) {
                                $annex->regenerateAgreementBody();
                            }
                        }
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    protected function resolveIndividualAmountDueForEvent(int $payingParticipantsCount): float
    {
        $event = $this->getOwnerRecord();

        return $event->resolvedPricePerPerson($payingParticipantsCount);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mergeContractMetaFromFormData(array $data): array
    {
        if (($data['agreement_type'] ?? null) !== Contract::TYPE_CUSTOM) {
            unset($data['payment_mode']);

            return $data;
        }

        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        if (isset($data['payment_mode'])) {
            $meta['payment_mode'] = $data['payment_mode'];
        }

        $data['meta'] = $meta;
        unset($data['payment_mode']);

        return $data;
    }

    protected function getParticipantPaymentOptions(): array
    {
        $event = $this->getOwnerRecord();
        $settlement = $event->activeSettlement;

        if (! $settlement) {
            return [];
        }

        return $settlement->participantPayments()
            ->orderBy('participant_name')
            ->get()
            ->mapWithKeys(function (EventSettlementParticipantPayment $payment): array {
                $amount = number_format((float) $payment->due_amount_pln, 2, ',', ' ');
                $label = $payment->participant_name." ({$amount} PLN)";

                return [$payment->id => $label];
            })
            ->all();
    }

    protected function resolveInsuranceFormDefaults(): array
    {
        $event = $this->getOwnerRecord();

        return [
            'insurance_policy_number' => $event->insurance_policy_number,
            'insurance_status' => $event->insurance_status ?: 'pending',
            'insurance_payment_status' => $event->insurance_payment_status ?: 'pending',
            'insurance_amount' => $event->insurance_amount,
            'insurance_paid_at' => $event->insurance_paid_at,
            'insurance_document_path' => $event->insurance_document_path,
            'insurance_terms' => $event->insurance_terms,
        ];
    }

    /**
     * Domyślne wartości formularza nowej umowy na podstawie bieżącej imprezy.
     */
    protected function resolveContractDefaultsFromEvent(): array
    {
        $event = $this->getOwnerRecord();
        $participantCount = max(1, (int) ($event->participant_count ?? 1));
        $pricePerPerson = $event->resolvedPricePerPerson($participantCount);
        $totalAmount = $pricePerPerson * $participantCount;

        return array_merge(
            app(ContractTfgSetupService::class)->fillFormFromEvent($event),
            $this->groupPricingService()->defaultsFromEvent($event),
            [
                'agreement_type' => Contract::TYPE_GROUP,
                'title' => 'Umowa — '.$event->name,
                'agreement_date' => now()->toDateString(),
                'ordering_parties' => app(ContractOrderingPartyService::class)->partiesFromEvent($event),
                'customer_name' => $event->client_name,
                'customer_email' => $event->client_email,
                'customer_phone' => $event->client_phone,
                'amount_due' => $totalAmount,
                'amount_paid' => 0,
                'currency' => 'PLN',
                'status' => 'sent',
                'payment_status' => 'pending',
                'selected_attachments' => $this->resolveSelectedAttachmentDefaults(),
            ],
        );
    }
}
