<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Actions\Contracts\CreateContractAnnexesAction;
use App\Actions\Contracts\GenerateEventContractAction;
use App\Actions\Finance\ApplyPaymentScheduleTemplateToEventAction;
use App\Filament\Forms\ContractAnnexFields;
use App\Filament\Forms\ContractCustomAgreementFields;
use App\Filament\Forms\ContractGenerationWizardFields;
use App\Filament\Forms\ContractGroupPricingFields;
use App\Filament\Forms\ContractOrderingPartyFields;
use App\Filament\Forms\ContractParticipantFields;
use App\Filament\Forms\ContractTemplateCustomValueFields;
use App\Filament\Forms\ContractTfgForm;
use App\Filament\Resources\ContractResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\RelationManagers\Concerns\ManagesContractAttachments;
use App\Filament\Resources\EventResource\RelationManagers\Concerns\ManagesContractOrderingParties;
use App\Filament\Resources\EventResource\RelationManagers\Concerns\ManagesContractPaymentSchedules;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Event;
use App\Models\EventSettlementParticipantPayment;
use App\Models\PaymentScheduleTemplate;
use App\Services\ContractOrderingPartyService;
use App\Services\ContractTfgSetupService;
use App\Services\Contracts\ContractPaymentProgressService;
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
use InvalidArgumentException;

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
                Tables\Actions\Action::make('generate_contract')
                    ->label('Generuj umowę')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->modalHeading('Generuj umowę')
                    ->modalWidth('5xl')
                    ->modalSubmitActionLabel('Generuj i pokaż link')
                    ->fillForm(fn (): array => ContractGenerationWizardFields::fillDefaults(
                        fn () => $this->getOwnerRecord(),
                    ))
                    ->form(ContractGenerationWizardFields::schema(
                        resolveEvent: fn () => $this->getOwnerRecord(),
                        attachmentOptions: fn (): array => $this->getSelectableAttachmentOptions(),
                        attachmentDefaults: fn (Get $get): array => $this->resolveSelectedAttachmentDefaults(
                            null,
                            filled($get('contract_template_id')) ? (int) $get('contract_template_id') : null,
                        ),
                    ))
                    ->action(function (array $data): void {
                        $event = $this->getOwnerRecord();
                        $data = $this->attachmentCatalogService()->mergeSelectedAttachmentsIntoFormData($data);
                        $data = ContractTemplateCustomValueFields::mergeIntoMeta($data);

                        if (($data['generation_mode'] ?? null) !== GenerateEventContractAction::MODE_GROUP_ORDERING) {
                            $slots = max(1, (int) ($data['participants_on_contract'] ?? 1));
                            $unit = round((float) ($data['unit_price'] ?? 0), 2);
                            if ($unit <= 0) {
                                $unit = $this->resolveIndividualAmountDueForEvent(
                                    max(1, (int) ($event->participant_count ?? 1))
                                );
                                $data['unit_price'] = $unit;
                            }
                            if (! (bool) ($data['override_amount_due'] ?? false)) {
                                $data['amount_due'] = round($unit * $slots, 2);
                            } elseif ((float) ($data['amount_due'] ?? 0) <= 0) {
                                $data['amount_due'] = round($unit * $slots, 2);
                            }
                        }

                        try {
                            $result = app(GenerateEventContractAction::class)($event, $data, auth()->id());
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->warning()
                                ->send();

                            return;
                        }

                        $primary = $result['primary'];
                        $companion = $result['companion'];
                        $body = match ($result['mode']) {
                            'individual_template', 'group_participants' => 'Wyślij ten wspólny link do wszystkich uczestników (ten sam URL). Umowa powstaje dopiero po wypełnieniu formularza — w /umowa płacą pierwszą ratę, kolejne w portalu / mailu.',
                            default => 'Utworzono umowę grupową (płatność zamawiającego).',
                        };
                        if ($companion) {
                            $body .= ' Dodatkowo: link dla uczestników do uzupełnienia danych.';
                        }

                        $actions = [
                            NotificationAction::make('open')
                                ->label('Otwórz link')
                                ->url($primary->public_link)
                                ->openUrlInNewTab(),
                        ];
                        if ($companion) {
                            $actions[] = NotificationAction::make('open_companion')
                                ->label('Link uczestników (dane)')
                                ->url($companion->public_link)
                                ->openUrlInNewTab();
                        }

                        Notification::make()
                            ->title('Umowa wygenerowana')
                            ->body($body)
                            ->success()
                            ->actions($actions)
                            ->send();
                    }),

                Tables\Actions\Action::make('apply_library_payment_schedule')
                    ->label('Szablon harmonogramu')
                    ->icon('heroicon-o-calendar-days')
                    ->color('gray')
                    ->visible(fn (): bool => Schema::hasTable('payment_schedule_templates'))
                    ->modalHeading('Zastosuj szablon harmonogramu na umowy')
                    ->modalDescription('Skopiuje szablon z biblioteki na imprezę i przeliczy raty na aktywnych umowach (zachowa dotychczasowe wpłaty).')
                    ->form([
                        Forms\Components\Select::make('payment_schedule_template_id')
                            ->label('Szablon z biblioteki')
                            ->options(fn (): array => PaymentScheduleTemplate::optionsForSelect())
                            ->searchable()
                            ->required(),
                        Forms\Components\Toggle::make('copy_to_event')
                            ->label('Zapisz też jako szablon tej imprezy')
                            ->default(true),
                    ])
                    ->action(function (array $data): void {
                        $event = $this->getOwnerRecord();
                        try {
                            $count = app(ApplyPaymentScheduleTemplateToEventAction::class)(
                                (int) $data['payment_schedule_template_id'],
                                $event,
                                (bool) ($data['copy_to_event'] ?? true),
                            );
                            Notification::make()
                                ->title($count > 0
                                    ? "Zastosowano szablon na {$count} umów."
                                    : 'Szablon zapisany. Brak umów do aktualizacji.')
                                ->success()
                                ->send();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\ActionGroup::make([
                Tables\Actions\Action::make('edit_event_tfg_defaults')
                    ->label('Domyślne dane TFG/UFG (raportowanie)')
                    ->icon('heroicon-o-building-library')
                    ->color('gray')
                    ->modalHeading('Domyślne dane TFG / UFG dla imprezy')
                    ->modalDescription('To nie jest płatność klienta. Ustawiasz tu domyślne pola do raportowania w Turystycznym Funduszu Gwarancyjnym / UFG (przedmiot, transport, kraj, sposób wpłat). Nowe umowy skopiują te wartości.')
                    ->modalWidth('3xl')
                    ->visible(fn (): bool => Schema::hasColumn('events', 'tfg_defaults'))
                    ->fillForm(fn (): array => app(ContractTfgSetupService::class)->defaultsFromEvent($this->getOwnerRecord()))
                    ->form(ContractTfgForm::schema(false, fn () => $this->getOwnerRecord()))
                    ->action(function (array $data): void {
                        $event = $this->getOwnerRecord();
                        $keys = [
                            'subject_code',
                            'payment_method_code',
                            'reservation_number',
                            'tfg_travelers_count',
                            'tfg_starts_at',
                            'tfg_ends_at',
                            'tfg_scope_type',
                            'tfg_country_code',
                            'tfg_locality',
                            'tfg_transport_code',
                            'tfg_icao_codes',
                        ];
                        $payload = [];
                        foreach ($keys as $key) {
                            if (array_key_exists($key, $data)) {
                                $payload[$key] = $data[$key];
                            }
                        }
                        $event->forceFill(['tfg_defaults' => $payload])->save();

                        Notification::make()
                            ->title('Zapisano domyślne dane TFG imprezy')
                            ->body('Nowe umowy wezmą te wartości jako domyślne do raportowania UFG.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('edit_event_insurance')
                    ->label('Ubezpieczenie')
                    ->icon('heroicon-o-shield-check')
                    ->color(fn (): string => $this->getOwnerRecord()->hasInsuranceDataSaved() ? 'success' : 'danger')
                    ->visible(fn (): bool => Schema::hasColumn('events', 'insurance_policy_number'))
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

                Tables\Actions\CreateAction::make()
                    ->label('Nowa umowa (zaawansowane)')
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
                    ->label('Więcej')
                    ->icon('heroicon-o-ellipsis-horizontal')
                    ->color('gray')
                    ->button(),
            ])
            ->actions([
                Tables\Actions\Action::make('open_public')
                    ->label('Otwórz link')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Contract $record) => $record->public_link)
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('edit_contract_page')
                    ->label('Edytuj')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Contract $record) => ContractResource::getUrl('edit', ['record' => $record])),

                Tables\Actions\Action::make('create_annex')
                    ->label('Aneks')
                    ->icon('heroicon-o-document-plus')
                    ->color('info')
                    ->visible(fn (Contract $record): bool => ! $record->isAnnex()
                        && $record->status !== 'template'
                        && $record->status !== 'cancelled')
                    ->form([
                        Forms\Components\TextInput::make('title')
                            ->label('Tytuł aneksu')
                            ->required()
                            ->default(fn (Contract $record): string => sprintf(
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
                            ->default(fn (Contract $record): float => (float) $record->amount_due)
                            ->required()
                            ->suffix('PLN'),
                        Forms\Components\TextInput::make('participant_count')
                            ->label('Liczba uczestników')
                            ->numeric()
                            ->minValue(1)
                            ->default(fn (Contract $record): int => (int) ($record->participant_count ?? 1)),
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
                            'amount_due' => (float) $record->amount_due,
                            'participant_count' => (int) ($record->participant_count ?? 1),
                            'event_start_date' => optional($record->event_start_date)?->toDateString(),
                            'event_end_date' => optional($record->event_end_date)?->toDateString(),
                        ],
                    ))
                    ->action(function (Contract $record, array $data): void {
                        try {
                            $annex = app(CreateContractAnnexesAction::class)([$record], $data)['created'][0];
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->warning()->send();

                            return;
                        }

                        Notification::make()
                            ->title('Utworzono aneks')
                            ->success()
                            ->actions([
                                NotificationAction::make('edit')
                                    ->label('Otwórz aneks')
                                    ->url(ContractResource::getUrl('edit', ['record' => $annex])),
                            ])
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Usuń')
                    ->requiresConfirmation()
                    ->modalHeading('Usunąć umowę?')
                    ->modalDescription('Usunięcie jest trwałe. Link klienta przestanie działać.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('create_annexes_bulk')
                    ->label('Utwórz aneksy (wybrane)')
                    ->icon('heroicon-o-document-plus')
                    ->color('info')
                    ->deselectRecordsAfterCompletion()
                    ->form([
                        Forms\Components\TextInput::make('title')
                            ->label('Tytuł aneksu (wspólny wzorzec)')
                            ->helperText('Możesz zostawić puste — wtedy „Aneks do umowy {numer}”.')
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('agreement_date')
                            ->label('Data aneksu')
                            ->default(now())
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('amount_due')
                            ->label('Kwota aneksu (opcjonalnie — wspólna dla wszystkich)')
                            ->numeric()
                            ->suffix('PLN')
                            ->helperText('Puste = zachowaj kwotę z każdej umowy źródłowej.'),
                        Forms\Components\DatePicker::make('event_start_date')
                            ->label('Nowa data rozpoczęcia (opcjonalnie)')
                            ->native(false),
                        Forms\Components\DatePicker::make('event_end_date')
                            ->label('Nowa data zakończenia (opcjonalnie)')
                            ->native(false),
                        ...ContractAnnexFields::createSchema(),
                    ])
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data): void {
                        $payload = $data;
                        if (! filled($payload['amount_due'] ?? null)) {
                            unset($payload['amount_due']);
                        }
                        if (! filled($payload['title'] ?? null)) {
                            unset($payload['title']);
                        }
                        if (! filled($payload['event_start_date'] ?? null)) {
                            unset($payload['event_start_date']);
                        }
                        if (! filled($payload['event_end_date'] ?? null)) {
                            unset($payload['event_end_date']);
                        }

                        try {
                            $result = app(CreateContractAnnexesAction::class)($records->all(), $payload);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->warning()->send();

                            return;
                        }

                        Notification::make()
                            ->title('Utworzono aneksy: '.count($result['created']))
                            ->body($result['skipped'] > 0
                                ? 'Pominięto: '.$result['skipped'].' (szablony/aneksy/anulowane).'
                                : 'Dane zamawiającego i uczestnika skopiowano z umów źródłowych.')
                            ->success()
                            ->send();
                    }),
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
