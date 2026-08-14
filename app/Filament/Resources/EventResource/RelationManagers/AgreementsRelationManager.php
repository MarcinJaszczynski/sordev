<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Filament\Forms\ContractCustomAgreementFields;
use App\Filament\Forms\ContractGroupPricingFields;
use App\Filament\Forms\ContractOrderingPartyFields;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\RelationManagers\Concerns\ManagesContractAttachments;
use App\Filament\Resources\EventResource\RelationManagers\Concerns\ManagesContractOrderingParties;
use App\Filament\Resources\EventResource\RelationManagers\Concerns\ManagesContractPaymentSchedules;
use App\Models\ContractTemplate;
use App\Models\EventAgreement;
use App\Models\EventSettlementParticipantPayment;
use App\Services\AgreementPaymentSyncService;
use App\Services\ContractOrderingPartyService;
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

/**
 * @deprecated Legacy UI umów. Kanon: {@see ContractsRelationManager} (tabela `contracts`).
 * Ładowany tylko gdy brak tabeli contracts — patrz ManageEventContracts.
 * Gdy tabela `contracts` istnieje, RM jest read-only (freeze tworzenia/edycji).
 */
class AgreementsRelationManager extends RelationManager
{
    use ManagesContractAttachments;
    use ManagesContractOrderingParties;
    use ManagesContractPaymentSchedules;

    protected static string $relationship = 'agreements';

    protected static ?string $title = 'Umowy i płatności (legacy)';

    protected static ?string $recordTitleAttribute = 'agreement_number';

    public function isReadOnly(): bool
    {
        return Schema::hasTable('contracts');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Podstawowe dane umowy')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Select::make('contract_template_id')
                        ->label('Szablon umowy')
                        ->options(fn (Get $get): array => ContractTemplate::optionsForSelect(
                            $get('agreement_type') ?? EventAgreement::TYPE_GROUP,
                        ))
                        ->searchable()
                        ->nullable()
                        ->live()
                        ->visible(fn (Get $get): bool => ($get('agreement_type') ?? EventAgreement::TYPE_GROUP) !== EventAgreement::TYPE_CUSTOM)
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $set('selected_attachments', $this->resolveSelectedAttachmentDefaults(
                                null,
                                filled($state) ? (int) $state : null,
                            ));
                        }),

                    Forms\Components\Select::make('agreement_type')
                        ->label('Typ umowy')
                        ->options(EventAgreement::$types)
                        ->default(EventAgreement::TYPE_GROUP)
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            if ($state === EventAgreement::TYPE_CUSTOM) {
                                $set('body_edit_mode', EventAgreement::BODY_EDIT_UPLOAD);
                                $set('contract_template_id', null);

                                return;
                            }

                            $set('body_edit_mode', EventAgreement::BODY_EDIT_TEMPLATE);

                            $event = $this->getOwnerRecord();
                            $participantCount = max(1, (int) ($event->participant_count ?? 1));
                            $pricePerPerson = $event->resolvedPricePerPerson($participantCount);

                            if ($state === EventAgreement::TYPE_INDIVIDUAL) {
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
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('agreement_number')
                        ->label('Numer umowy')
                        ->maxLength(255)
                        ->helperText('Możesz pozostawić puste, numer zostanie nadany automatycznie.'),

                    Forms\Components\DatePicker::make('agreement_date')
                        ->label('Data umowy')
                        ->native(false)
                        ->default(now()),
                ]),

            Forms\Components\Section::make('Dane imprezy')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('event_name')
                        ->label('Nazwa imprezy')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('participant_count')
                        ->label('Liczba uczestników')
                        ->numeric()
                        ->minValue(1)
                        ->default(fn () => (int) ($this->getOwnerRecord()->participant_count ?? 1))
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, Get $get) => ContractGroupPricingFields::syncGroupTotal($set, $get, fn () => $this->getOwnerRecord())),

                    Forms\Components\DatePicker::make('event_start_date')
                        ->label('Data rozpoczęcia')
                        ->native(false),

                    Forms\Components\DatePicker::make('event_end_date')
                        ->label('Data zakończenia')
                        ->native(false),

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
                ->columns(['default' => 1, 'md' => 2])
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
                        ->options(EventAgreement::$statuses)
                        ->default('sent')
                        ->required(),

                    Forms\Components\Select::make('payment_status')
                        ->label('Status płatności')
                        ->options(EventAgreement::$paymentStatuses)
                        ->default('pending')
                        ->required(),

                    Forms\Components\Select::make('payment_method')
                        ->label('Metoda płatności')
                        ->options(EventAgreement::$paymentMethods)
                        ->nullable(),

                    Forms\Components\DateTimePicker::make('signed_at')
                        ->label('Data zawarcia')
                        ->nullable(),

                    Forms\Components\DatePicker::make('paid_at')
                        ->label('Data płatności')
                        ->nullable(),
                ]),

            Forms\Components\Section::make('Treść i załączniki')
                ->schema([
                    ...ContractCustomAgreementFields::contentSchema(),

                    Forms\Components\CheckboxList::make('selected_attachments')
                        ->label('Wybierz gotowe załączniki')
                        ->options(fn (): array => $this->getSelectableAttachmentOptions())
                        ->columns(1)
                        ->helperText('Domyślnie zaznaczane są załączniki globalne lub przypisane do wybranego szablonu.')
                        ->default(fn (?EventAgreement $record, Get $get): array => $this->resolveSelectedAttachmentDefaults(
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
                Tables\Columns\TextColumn::make('agreement_number')
                    ->label('Numer')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Umowa')
                    ->searchable()
                    ->wrap()
                    ->description(fn (EventAgreement $record) => $record->event_name ?: '—'),

                Tables\Columns\BadgeColumn::make('agreement_type')
                    ->label('Typ')
                    ->formatStateUsing(fn (EventAgreement $record) => $record->agreement_type_label)
                    ->colors([
                        'info' => EventAgreement::TYPE_GROUP,
                        'primary' => EventAgreement::TYPE_INDIVIDUAL,
                    ]),

                Tables\Columns\TextColumn::make('ordering_parties_label')
                    ->label('Zamawiający')
                    ->state(fn (EventAgreement $record) => app(ContractOrderingPartyService::class)->formattedPartyNames($record))
                    ->description(fn (EventAgreement $record) => $record->ordering_party_notes)
                    ->wrap()
                    ->searchable(['customer_name', 'customer_email']),

                Tables\Columns\TextColumn::make('amount_due')
                    ->label('Do zapłaty')
                    ->money('PLN')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Płatność')
                    ->formatStateUsing(fn ($state) => EventAgreement::$paymentStatuses[$state] ?? $state)
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => 'failed',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Umowa')
                    ->formatStateUsing(fn ($state) => EventAgreement::$statuses[$state] ?? $state)
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'sent',
                        'warning' => 'signed',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('public_link')
                    ->label('Link klienta')
                    ->state(fn (EventAgreement $record) => $record->public_link)
                    ->copyable()
                    ->copyMessage('Skopiowano link')
                    ->limit(32),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Opłacono')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('edit_event_insurance')
                    ->label('Ubezpieczenie')
                    ->icon('heroicon-o-shield-check')
                    ->color(fn (): string => $this->getOwnerRecord()->hasInsuranceDataSaved() ? 'success' : 'danger')
                    ->visible(fn (): bool => Schema::hasColumn('events', 'insurance_policy_number')
                        && $this->getOwnerRecord()->requiresInsuranceWorkflow())
                    ->url(fn (): string => EventResource::getUrl('day-insurances', ['record' => $this->getOwnerRecord()])),

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
                    ->form([
                        Forms\Components\Select::make('contract_template_id')
                            ->label('Szablon umowy')
                            ->options(fn (): array => ContractTemplate::optionsForSelect(EventAgreement::TYPE_GROUP))
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
                            ->minValue(1),

                        Forms\Components\CheckboxList::make('selected_attachments')
                            ->label('Załączniki do dołączenia')
                            ->options(fn (): array => $this->getSelectableAttachmentOptions())
                            ->columns(1)
                            ->default(fn (Get $get): array => $this->resolveSelectedAttachmentDefaults(
                                null,
                                filled($get('contract_template_id')) ? (int) $get('contract_template_id') : null,
                            ))
                            ->helperText('Zaznaczone pliki z systemu lub katalogu pliki będą dostępne klientowi razem z umową.'),
                    ])
                    ->action(function (array $data): void {
                        $event = $this->getOwnerRecord();
                        $data = $this->attachmentCatalogService()->mergeSelectedAttachmentsIntoFormData($data);
                        $attachments = $data['attachments'] ?? [];

                        $agreement = EventAgreement::create([
                            'event_id' => $event->id,
                            'contract_template_id' => $data['contract_template_id'] ?? null,
                            'agreement_type' => EventAgreement::TYPE_GROUP,
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
                        ]);

                        $agreement->regenerateAgreementBody();

                        $this->syncOrderingPartiesForEventAgreement(
                            $agreement,
                            app(ContractOrderingPartyService::class)->partiesFromEvent($event),
                        );

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
                            ->options(fn (): array => ContractTemplate::optionsForSelect(EventAgreement::TYPE_INDIVIDUAL))
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
                            ->where('agreement_type', EventAgreement::TYPE_INDIVIDUAL)
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
                        $agreement = EventAgreement::create([
                            'event_id' => $event->id,
                            'contract_template_id' => $data['contract_template_id'] ?? null,
                            'agreement_type' => EventAgreement::TYPE_INDIVIDUAL,
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
                        ]);

                        $agreement->regenerateAgreementBody();

                        $this->syncOrderingPartiesForEventAgreement(
                            $agreement,
                            app(ContractOrderingPartyService::class)->partiesFromEvent($event),
                        );

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
                    ->fillForm(fn (): array => $this->resolveAgreementDefaultsFromEvent())
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

                        $data['agreement_type'] = $data['agreement_type'] ?? EventAgreement::TYPE_GROUP;

                        if (($data['agreement_type'] ?? null) === EventAgreement::TYPE_INDIVIDUAL) {
                            $data['participant_count'] = 1;
                        }

                        if (! empty($data['participant_payment_id'])) {
                            $participantPayment = EventSettlementParticipantPayment::query()->find($data['participant_payment_id']);

                            if ($participantPayment) {
                                $data['participant_name'] = $data['participant_name'] ?? $participantPayment->participant_name;

                                if (($data['agreement_type'] ?? null) === EventAgreement::TYPE_INDIVIDUAL && (! isset($data['amount_due']) || (float) $data['amount_due'] <= 0)) {
                                    $data['amount_due'] = (float) $participantPayment->due_amount_pln;
                                }
                            }
                        }

                        if (($data['agreement_type'] ?? null) === EventAgreement::TYPE_INDIVIDUAL && (! isset($data['amount_due']) || (float) $data['amount_due'] <= 0)) {
                            $data['amount_due'] = $this->resolveIndividualAmountDueForEvent(
                                max(1, (int) ($event->participant_count ?? 1))
                            );
                        }

                        $data['status'] = $data['status'] ?? 'sent';
                        $data['payment_status'] = $data['payment_status'] ?? 'pending';

                        return $this->mergeGroupPricingIntoFormData($data);
                    })
                    ->after(function (EventAgreement $record, array $data): void {
                        $this->syncOrderingPartiesForEventAgreement(
                            $record,
                            $data['ordering_parties'] ?? [],
                            $data['ordering_party_notes'] ?? null,
                        );

                        $this->syncPaymentSchedulesForEventAgreement($record->fresh(), $data);

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
                    ->url(fn (EventAgreement $record) => $record->public_link)
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('regenerate')
                    ->label('Regeneruj treść')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (EventAgreement $record): bool => $record->shouldAutoGenerateAgreementBody())
                    ->action(function (EventAgreement $record): void {
                        $record->regenerateAgreementBody();

                        Notification::make()
                            ->title('Treść umowy zaktualizowana')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('mark_paid')
                    ->label('Oznacz jako opłacona')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (EventAgreement $record) => $record->payment_status !== 'paid')
                    ->action(function (EventAgreement $record): void {
                        $record->update([
                            'status' => 'completed',
                            'payment_status' => 'paid',
                            'amount_paid' => (float) $record->amount_due,
                            'paid_at' => now(),
                        ]);

                        app(AgreementPaymentSyncService::class)->sync($record->fresh());

                        Notification::make()
                            ->title('Umowa oznaczona jako opłacona')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->fillForm(fn (EventAgreement $record): array => array_merge(
                        $record->toArray(),
                        [
                            'ordering_parties' => app(ContractOrderingPartyService::class)->partiesToFormState($record),
                            'payment_schedules' => $this->groupPricingService()->schedulesToFormState($record),
                        ],
                    ))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data = $this->mergeSelectedAttachmentsIntoData($data);

                        return $this->mergeOrderingPartiesIntoFormData($data);
                    })
                    ->after(function (EventAgreement $record, array $data): void {
                        $this->syncOrderingPartiesForEventAgreement(
                            $record,
                            $data['ordering_parties'] ?? [],
                            $data['ordering_party_notes'] ?? null,
                        );

                        $this->syncPaymentSchedulesForEventAgreement($record->fresh(), $data);
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

    protected function resolveAgreementDefaultsFromEvent(): array
    {
        $event = $this->getOwnerRecord();

        return array_merge($this->groupPricingService()->defaultsFromEvent($event), [
            'agreement_type' => EventAgreement::TYPE_GROUP,
            'title' => 'Umowa — '.$event->name,
            'agreement_date' => now()->toDateString(),
            'event_name' => $event->name,
            'event_start_date' => optional($event->start_date)?->toDateString(),
            'event_end_date' => optional($event->end_date)?->toDateString(),
            'ordering_parties' => app(ContractOrderingPartyService::class)->partiesFromEvent($event),
            'customer_name' => $event->client_name,
            'customer_email' => $event->client_email,
            'customer_phone' => $event->client_phone,
            'amount_paid' => 0,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'selected_attachments' => $this->resolveSelectedAttachmentDefaults(),
        ]);
    }
}
