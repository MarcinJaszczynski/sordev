<?php

namespace App\Livewire;

use App\Actions\Events\UpsertEventParticipantAction;
use App\Actions\Finance\ApplyEventPaymentScheduleTemplateAction;
use App\Actions\Finance\ApplyPaymentScheduleTemplateToEventAction;
use App\Actions\Finance\RecordParticipantPaymentAction;
use App\Actions\Finance\UpsertEventPaymentInstallmentTemplateAction;
use App\Data\RecordParticipantPaymentData;
use App\Data\UpsertEventParticipantData;
use App\Filament\Forms\CurrencyConversionFields;
use App\Filament\Forms\EventParticipantFormFields;
use App\Filament\Forms\EventPaymentInstallmentTemplateFields;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventSettlementParticipantPaymentEntry;
use App\Models\PaymentScheduleTemplate;
use App\Services\EventParticipantPropagationService;
use App\Services\EventPaymentInstallmentTemplateService;
use App\Services\ParticipantPaymentBalanceService;
use App\Services\ParticipantPaymentLedgerService;
use App\Services\ParticipantPaymentReminderService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;
use Livewire\Component;

class ParticipantPaymentsLedger extends Component implements HasActions, HasForms
{
    use \App\Filament\Concerns\InteractsWithClientInvoiceRequestActions;
    use \App\Filament\Concerns\ShowsParticipantPaymentBalance;
    use InteractsWithActions;
    use InteractsWithForms;

    public int $settlementId;

    public int $eventId;

    public ?int $focusPaymentId = null;

    public function mount(int $settlementId, int $eventId, ?int $focusPaymentId = null): void
    {
        $this->settlementId = $settlementId;
        $this->eventId = $eventId;

        $settlement = EventSettlement::query()->findOrFail($settlementId);
        abort_unless((int) $settlement->event_id === $eventId, 404);

        $requestedFocus = $focusPaymentId ?: (request()->integer('payment') ?: null);
        if ($requestedFocus) {
            $belongs = EventSettlementParticipantPayment::query()
                ->whereKey($requestedFocus)
                ->where('settlement_id', $settlementId)
                ->exists();
            $this->focusPaymentId = $belongs ? (int) $requestedFocus : null;
        }

        // Uczestnicy z listy imprezy mają być widoczni we wpłatach (wiele transz na osobę).
        if (Schema::hasTable('event_participants')) {
            app(EventParticipantPropagationService::class)->propagateToPayments(
                Event::query()->findOrFail($eventId)
            );
        }

        if ($this->focusPaymentId) {
            $focusId = (int) $this->focusPaymentId;
            $this->js(<<<JS
                requestAnimationFrame(() => {
                    const el = document.getElementById('participant-payment-focus-{$focusId}');
                    if (!el) return;
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            JS);
        }
    }

    #[On('settlement-data-changed')]
    public function refreshLedger(): void {}

    public function render()
    {
        $event = Event::query()->find($this->eventId);
        $priceContext = $event
            ? EventParticipantFormFields::priceContextForEvent($event)
            : [
                'event_code' => null,
                'price_per_person' => 0.0,
                'price_label' => '—',
                'foreign_hint' => null,
                'foreign_prices' => [],
            ];

        $gapAnalysis = $event
            ? app(ParticipantPaymentBalanceService::class)->eventAggregate($event)
            : null;

        $templateService = app(EventPaymentInstallmentTemplateService::class);
        $schedulePreview = null;
        if ($event && \Illuminate\Support\Facades\Schema::hasTable('event_payment_installment_templates')) {
            $unit = (float) $event->resolvedPricePerPerson();
            $previewRows = $templateService->materializeForBase($event, $unit);
            $schedulePreview = [
                'count' => count($previewRows),
                'unit_price' => $unit,
                'rows' => $previewRows,
            ];
        }

        return view('livewire.participant-payments-ledger', [
            'rows' => $this->ledgerRows(),
            'event' => $event,
            'priceContext' => $priceContext,
            'gapAnalysis' => $gapAnalysis,
            'schedulePreview' => $schedulePreview,
        ]);
    }

    public function addParticipantAction(): Action
    {
        $event = Event::query()->findOrFail($this->eventId);
        $priceContext = EventParticipantFormFields::priceContextForEvent($event);
        $defaultDue = round((float) ($priceContext['price_per_person'] ?? 0), 2);

        return Action::make('addParticipant')
            ->label('Dodaj uczestnika')
            ->icon('heroicon-o-user-plus')
            ->color('primary')
            ->slideOver()
            ->modalWidth('md')
            ->modalHeading('Dodaj uczestnika')
            ->modalDescription('Dane imprezy i cena/os. podstawiają się z cennika. Wpłaty możesz dodać od razu albo później w wielu transzach.')
            ->fillForm([
                'due_amount_pln' => $defaultDue,
                'waive_payment' => false,
                'first_payment_paid_at' => now(),
                'first_payment_method' => 'transfer',
            ])
            ->form([
                Forms\Components\Section::make('Dane uczestnika')
                    ->columns(2)
                    ->schema(EventParticipantFormFields::identityFields()),
                Forms\Components\Section::make('Należność')
                    ->columns(2)
                    ->schema(EventParticipantFormFields::paymentExtrasFields($priceContext)),
            ])
            ->action(function (array $data) use ($defaultDue): void {
                $event = Event::query()->findOrFail($this->eventId);
                $waive = (bool) ($data['waive_payment'] ?? false);
                $due = $waive ? 0.0 : (isset($data['due_amount_pln']) ? (float) $data['due_amount_pln'] : $defaultDue);

                app(UpsertEventParticipantAction::class)(new UpsertEventParticipantData(
                    event: $event,
                    firstName: $data['first_name'] ?? null,
                    lastName: $data['last_name'] ?? null,
                    gender: $data['gender'] ?? null,
                    birthDate: $data['birth_date'] ?? null,
                    pesel: $data['pesel'] ?? null,
                    email: $data['email'] ?? null,
                    phone: $data['phone'] ?? null,
                    bookingReference: $data['booking_reference'] ?? null,
                    diet: $data['diet'] ?? null,
                    parentConsent: (bool) ($data['parent_consent'] ?? false),
                    ensurePayment: true,
                    dueAmountPln: $due,
                    firstPaymentAmountPln: (! $waive && filled($data['first_payment_amount_pln'] ?? null))
                        ? (float) $data['first_payment_amount_pln']
                        : null,
                    firstPaymentPaidAt: $data['first_payment_paid_at'] ?? null,
                    firstPaymentMethod: $data['first_payment_method'] ?? 'transfer',
                ));

                $this->notifyChanged($waive
                    ? 'Dodano uczestnika bez opłaty.'
                    : 'Dodano uczestnika (lista + wpłaty).');
            });
    }

    public function editScheduleTemplateAction(): Action
    {
        $event = Event::query()->findOrFail($this->eventId);
        $templateService = app(EventPaymentInstallmentTemplateService::class);

        return Action::make('editScheduleTemplate')
            ->label('Harmonogram wpłat')
            ->icon('heroicon-o-calendar-days')
            ->color('gray')
            ->modalHeading('Szablon harmonogramu wpłat')
            ->modalDescription('Względne terminy (D±N) i udziały. Po zapisie możesz od razu zastosować szablon na umowy (schemat → transze).')
            ->fillForm([
                'installment_templates' => $templateService->templateToFormState($event),
                'apply_to_contracts' => true,
            ])
            ->form([
                ...EventPaymentInstallmentTemplateFields::schema(),
                Forms\Components\Toggle::make('apply_to_contracts')
                    ->label('Zastosuj od razu na umowy imprezy')
                    ->helperText('Przełącza schemat na „w transzach” i aktualizuje raty (zachowuje dotychczasowe wpłaty).')
                    ->default(true),
            ])
            ->modalSubmitActionLabel('Zapisz')
            ->action(function (array $data): void {
                $event = Event::query()->findOrFail($this->eventId);
                $rows = $data['installment_templates'] ?? [];

                try {
                    app(UpsertEventPaymentInstallmentTemplateAction::class)($event, $rows);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                if (! (bool) ($data['apply_to_contracts'] ?? false)) {
                    $this->notifyChanged('Zapisano szablon harmonogramu.');

                    return;
                }

                try {
                    $count = app(ApplyEventPaymentScheduleTemplateAction::class)($event);
                    $this->notifyChanged($count > 0
                        ? "Zapisano szablon i zastosowano na {$count} umów."
                        : 'Zapisano szablon. Brak umów do aktualizacji.');
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public function applyScheduleTemplateAction(): Action
    {
        return Action::make('applyScheduleTemplate')
            ->label('Zastosuj na umowy')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Zastosować szablon na umowy?')
            ->modalDescription('Przełączy schemat płatności na „w transzach” i zaktualizuje raty (zachowa dotychczasowe wpłaty na ratach).')
            ->action(function (): void {
                $event = Event::query()->findOrFail($this->eventId);
                try {
                    $count = app(ApplyEventPaymentScheduleTemplateAction::class)($event);
                    $this->notifyChanged($count > 0
                        ? "Zastosowano szablon na {$count} umów."
                        : 'Brak umów do aktualizacji (lub pusty szablon).');
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public function applyLibraryScheduleTemplateAction(): Action
    {
        return Action::make('applyLibraryScheduleTemplate')
            ->label('Z biblioteki…')
            ->icon('heroicon-o-bookmark-square')
            ->color('gray')
            ->visible(fn (): bool => Schema::hasTable('payment_schedule_templates'))
            ->modalHeading('Zastosuj szablon z biblioteki')
            ->modalDescription('Wybierz globalny szablon harmonogramu. Skopiuje go na imprezę i przeliczy raty na umowach (zachowa wpłaty).')
            ->form([
                Forms\Components\Select::make('payment_schedule_template_id')
                    ->label('Szablon harmonogramu')
                    ->options(fn (): array => PaymentScheduleTemplate::optionsForSelect())
                    ->searchable()
                    ->required(),
                Forms\Components\Toggle::make('copy_to_event')
                    ->label('Zapisz też jako szablon tej imprezy')
                    ->default(true),
            ])
            ->modalSubmitActionLabel('Zastosuj')
            ->action(function (array $data): void {
                $event = Event::query()->findOrFail($this->eventId);
                try {
                    $count = app(ApplyPaymentScheduleTemplateToEventAction::class)(
                        (int) $data['payment_schedule_template_id'],
                        $event,
                        (bool) ($data['copy_to_event'] ?? true),
                    );
                    $this->notifyChanged($count > 0
                        ? "Zastosowano szablon biblioteki na {$count} umów."
                        : 'Szablon skopiowany. Brak umów do aktualizacji.');
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public function addEntryAction(): Action
    {
        $plnId = \App\Models\Currency::defaultPlnId();

        return Action::make('addEntry')
            ->icon('heroicon-o-plus')
            ->color('gray')
            ->iconButton()
            ->slideOver()
            ->modalWidth('md')
            ->modalHeading('Dodaj wpłatę')
            ->modalDescription('Zaliczka / Dopłata — panel jak w Finanse → Koszty.')
            ->fillForm(function (array $arguments) use ($plnId): array {
                $payerName = null;
                $paymentId = (int) ($arguments['paymentId'] ?? 0);
                if ($paymentId > 0) {
                    $payment = EventSettlementParticipantPayment::query()
                        ->whereKey($paymentId)
                        ->where('settlement_id', $this->settlementId)
                        ->first();
                    $payerName = $payment?->participant_name;
                }

                return [
                    'paid_at' => now(),
                    'currency_id' => $plnId,
                    'rate' => 1,
                    'convert_to_pln' => false,
                    'payment_kind' => EventSettlementParticipantPaymentEntry::KIND_REGULAR,
                    'payment_method' => 'transfer',
                    'payer_name' => $payerName,
                ];
            })
            ->form([
                Forms\Components\DatePicker::make('paid_at')
                    ->label('Data wpłaty')
                    ->required()
                    ->default(now()),
                CurrencyConversionFields::currencySelect('currency_id', required: false)
                    ->nullable()
                    ->afterStateUpdated(function ($state, Forms\Set $set): void {
                        if (! CurrencyConversionFields::isForeignCurrency($state)) {
                            $set('rate', 1);
                            $set('amount', null);
                            $set('convert_to_pln', false);

                            return;
                        }

                        $currency = \App\Models\Currency::query()->find($state);
                        $rate = (float) ($currency?->exchange_rate ?: 0);
                        $set('rate', $rate > 0 ? $rate : null);
                        $set('convert_to_pln', false);
                    }),
                Forms\Components\Toggle::make('convert_to_pln')
                    ->label('Przelicz na PLN')
                    ->helperText('Wyłączone: wpłata zostaje w walucie (np. zbiórka w autokarze). Włącz, by doliczyć ekwiwalent do salda PLN.')
                    ->default(false)
                    ->inline(false)
                    ->live()
                    ->visible(fn (Forms\Get $get): bool => CurrencyConversionFields::isForeignCurrency($get('currency_id'))),
                Forms\Components\TextInput::make('amount')
                    ->label('Kwota w walucie')
                    ->numeric()
                    ->minValue(0.01)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                        if (! CurrencyConversionFields::isForeignCurrency($get('currency_id'))) {
                            return;
                        }
                        if (! (bool) ($get('convert_to_pln') ?? false)) {
                            $set('amount_pln', null);

                            return;
                        }
                        $amount = (float) ($state ?: 0);
                        $rate = (float) ($get('rate') ?: 0);
                        $set('amount_pln', ($amount > 0 && $rate > 0) ? round($amount * $rate, 2) : null);
                    })
                    ->visible(fn (Forms\Get $get): bool => CurrencyConversionFields::isForeignCurrency($get('currency_id')))
                    ->required(fn (Forms\Get $get): bool => CurrencyConversionFields::isForeignCurrency($get('currency_id'))),
                Forms\Components\TextInput::make('rate')
                    ->label('Kurs do PLN')
                    ->numeric()
                    ->minValue(0.0001)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                        if (! CurrencyConversionFields::isForeignCurrency($get('currency_id'))) {
                            return;
                        }
                        if (! (bool) ($get('convert_to_pln') ?? false)) {
                            return;
                        }
                        $amount = (float) ($get('amount') ?: 0);
                        $rate = (float) ($state ?: 0);
                        $set('amount_pln', ($amount > 0 && $rate > 0) ? round($amount * $rate, 2) : null);
                    })
                    ->visible(fn (Forms\Get $get): bool => CurrencyConversionFields::isForeignCurrency($get('currency_id'))
                        && (bool) ($get('convert_to_pln') ?? false))
                    ->required(fn (Forms\Get $get): bool => CurrencyConversionFields::isForeignCurrency($get('currency_id'))
                        && (bool) ($get('convert_to_pln') ?? false)),
                Forms\Components\TextInput::make('amount_pln')
                    ->label(fn (Forms\Get $get): string => CurrencyConversionFields::isForeignCurrency($get('currency_id'))
                        ? 'Kwota PLN (po kursie)'
                        : 'Kwota (PLN)')
                    ->numeric()
                    ->required(fn (Forms\Get $get): bool => ! CurrencyConversionFields::isForeignCurrency($get('currency_id'))
                        || (bool) ($get('convert_to_pln') ?? false))
                    ->minValue(0.01)
                    ->suffix('PLN')
                    ->visible(fn (Forms\Get $get): bool => ! CurrencyConversionFields::isForeignCurrency($get('currency_id'))
                        || (bool) ($get('convert_to_pln') ?? false))
                    ->readOnly(fn (Forms\Get $get): bool => CurrencyConversionFields::isForeignCurrency($get('currency_id'))),
                Forms\Components\TextInput::make('payer_name')
                    ->label('Płatnik')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('bank_transfer_description')
                    ->label('Opis przelewu')
                    ->maxLength(255),
                Forms\Components\Select::make('payment_kind')
                    ->label('Rodzaj wpłaty')
                    ->options(EventSettlementParticipantPaymentEntry::$paymentKinds)
                    ->default(EventSettlementParticipantPaymentEntry::KIND_REGULAR)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                        if ($state !== EventSettlementParticipantPaymentEntry::KIND_PILOT_ON_SITE) {
                            return;
                        }
                        if (CurrencyConversionFields::isForeignCurrency($get('currency_id'))) {
                            return;
                        }
                        $eur = \App\Models\Currency::query()
                            ->where(function ($q): void {
                                $q->where('code', 'EUR')->orWhere('symbol', 'EUR');
                            })
                            ->first();
                        if ($eur) {
                            $set('currency_id', $eur->id);
                            $rate = (float) ($eur->exchange_rate ?: 0);
                            $set('rate', $rate > 0 ? $rate : null);
                            $set('convert_to_pln', false);
                        }
                    }),
                Forms\Components\Select::make('payment_method')
                    ->label('Forma płatności')
                    ->options(EventSettlementParticipantPayment::$paymentMethods)
                    ->default('transfer'),
                Forms\Components\TextInput::make('document_number')
                    ->label('Nr faktury')
                    ->maxLength(255),
                Forms\Components\FileUpload::make('invoice_files')
                    ->label('Pliki faktury')
                    ->multiple()
                    ->disk('public')
                    ->directory('event-participant-invoices')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                    ])
                    ->helperText('Po wgraniu w tabeli pojawi się badge „Faktura wgrana”.'),
            ])
            ->action(function (array $data, array $arguments): void {
                $payment = $this->findPayment((int) ($arguments['paymentId'] ?? 0));
                $currencyId = filled($data['currency_id'] ?? null) ? (int) $data['currency_id'] : null;
                $isForeign = CurrencyConversionFields::isForeignCurrency($currencyId);
                $convertToPln = $isForeign ? (bool) ($data['convert_to_pln'] ?? false) : true;

                $amountForeign = $isForeign ? (float) ($data['amount'] ?? 0) : null;
                $rate = ($isForeign && $convertToPln) ? (float) ($data['rate'] ?? 0) : ($isForeign ? (float) ($data['rate'] ?? 0) ?: null : null);
                $amountPln = $isForeign
                    ? ($convertToPln
                        ? round(((float) ($data['amount'] ?? 0)) * ((float) ($data['rate'] ?? 0)), 2)
                        : 0.0)
                    : (float) ($data['amount_pln'] ?? 0);

                if ($amountPln <= 0 && ($amountForeign === null || $amountForeign <= 0)) {
                    Notification::make()->title('Kwota wpłaty musi być większa od zera.')->danger()->send();

                    return;
                }

                app(RecordParticipantPaymentAction::class)(new RecordParticipantPaymentData(
                    payment: $payment,
                    amount: $amountPln,
                    paidAt: $data['paid_at'] ?? now(),
                    paymentMethod: $data['payment_method'] ?? 'transfer',
                    payerName: $data['payer_name'] ?? null,
                    source: EventSettlementParticipantPaymentEntry::SOURCE_MANUAL,
                    bankTransferDescription: $data['bank_transfer_description'] ?? null,
                    paymentKind: $data['payment_kind'] ?? EventSettlementParticipantPaymentEntry::KIND_REGULAR,
                    amountForeign: $amountForeign,
                    rate: $rate,
                    currencyId: $currencyId,
                ));

                $docNumber = trim((string) ($data['document_number'] ?? ''));
                $files = $data['invoice_files'] ?? [];
                if (! is_array($files)) {
                    $files = filled($files) ? [$files] : [];
                }

                if ($docNumber !== '' || $files !== []) {
                    if ($docNumber !== '') {
                        $payment->forceFill(['document_number' => $docNumber])->save();
                    }
                    $this->attachParticipantInvoiceFiles($payment, $files, $docNumber !== '' ? $docNumber : null);
                }

                $this->notifyChanged('Wpłata została zapisana.');
            });
    }

    public function removeEntryAction(): Action
    {
        return Action::make('removeEntry')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->iconButton()
            ->requiresConfirmation()
            ->modalHeading('Usuń wpłatę')
            ->modalDescription('Czy na pewno usunąć tę wpłatę z historii?')
            ->action(function (array $arguments): void {
                $entry = EventSettlementParticipantPaymentEntry::query()
                    ->whereKey((int) ($arguments['entryId'] ?? 0))
                    ->whereHas('participantPayment', fn ($query) => $query
                        ->where('settlement_id', $this->settlementId)
                        ->whereHas('settlement', fn ($settlementQuery) => $settlementQuery->where('event_id', $this->eventId)))
                    ->firstOrFail();

                app(ParticipantPaymentLedgerService::class)->removeEntry($entry);

                $this->notifyChanged('Wpłata została usunięta.');
            });
    }

    public function removeParticipantAction(): Action
    {
        return Action::make('removeParticipant')
            ->label('Usuń')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Usuń uczestnika z rozliczenia')
            ->modalDescription('Czy na pewno usunąć uczestnika i całą historię wpłat?')
            ->action(function (array $arguments): void {
                $payment = $this->findPayment((int) ($arguments['paymentId'] ?? 0));

                if ($payment->hasLinkedAgreementsBesides()) {
                    Notification::make()
                        ->title('Nie można usunąć')
                        ->body('Uczestnik ma powiązane umowy lub aneksy. Odłącz je przed usunięciem.')
                        ->danger()
                        ->send();

                    return;
                }

                $payment->delete();

                $this->notifyChanged('Uczestnik został usunięty z rozliczenia.');
            });
    }

    public function sendReminderAction(): Action
    {
        return Action::make('sendReminder')
            ->label('Wyślij przypomnienie')
            ->icon('heroicon-o-envelope')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Wyślij przypomnienie o dopłacie')
            ->modalDescription('E-mail zostanie wysłany na adres uczestnika z informacją o pozostałej kwocie.')
            ->action(function (array $arguments): void {
                $payment = $this->findPayment((int) ($arguments['paymentId'] ?? 0));
                $result = app(ParticipantPaymentReminderService::class)->sendReminder($payment);

                if (! ($result['sent'] ?? false)) {
                    Notification::make()
                        ->title('Nie wysłano przypomnienia')
                        ->body((string) ($result['message'] ?? 'Brak adresu e-mail uczestnika.'))
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Przypomnienie wysłane')
                    ->body((string) ($result['message'] ?? 'E-mail został zakolejkowany.'))
                    ->success()
                    ->send();
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function ledgerRows(): Collection
    {
        if (! Schema::hasTable('event_settlement_participant_payments')) {
            return collect();
        }

        $balanceService = app(ParticipantPaymentBalanceService::class);

        return EventSettlementParticipantPayment::query()
            ->where('settlement_id', $this->settlementId)
            ->whereHas('settlement', fn ($query) => $query->where('event_id', $this->eventId))
            ->with([
                'entries' => fn ($query) => $query->with('currency')->orderBy('paid_at')->orderBy('id'),
                'eventParticipant' => fn ($query) => $query->where('event_id', $this->eventId),
                'contracts' => fn ($query) => $query->where('event_id', $this->eventId),
            ])
            ->orderBy('participant_name')
            ->get()
            ->map(function (EventSettlementParticipantPayment $payment) use ($balanceService): array {
                $balance = $balanceService->balanceRow($payment);
                $participant = $payment->eventParticipant;
                $invoiceMeta = $this->resolveInvoiceMeta($payment);

                return [
                    'payment' => $payment,
                    'participant_name' => $payment->participant_name,
                    'booking_reference' => $payment->booking_reference,
                    'email' => $participant?->email,
                    'phone' => $participant?->phone,
                    'due_pln' => (float) ($balance['due_pln'] ?? 0),
                    'paid_pln' => (float) ($balance['paid_pln'] ?? 0),
                    'remaining_pln' => (float) ($balance['remaining_pln'] ?? 0),
                    'difference_pln' => (float) ($balance['remaining_pln'] ?? 0),
                    'coverage_status' => (string) ($balance['coverage_status'] ?? 'shortfall'),
                    'coverage_label' => (string) ($balance['display_status_label'] ?? $balance['coverage_label'] ?? ''),
                    'next_due_date' => $balance['next_due_date'] instanceof \Carbon\CarbonInterface
                        ? $balance['next_due_date']->format('Y-m-d')
                        : null,
                    'next_due_amount' => $balance['next_due_amount'] !== null
                        ? (float) $balance['next_due_amount']
                        : null,
                    'installment_label' => $balance['installment_label'] ?? null,
                    'entries' => $payment->entries,
                    'has_invoice_file' => $invoiceMeta['has_file'],
                    'invoice_label' => $invoiceMeta['label'],
                    'invoice_url' => $invoiceMeta['url'],
                    'document_number' => $payment->document_number,
                ];
            });
    }

    /**
     * @param  list<string>  $storedPaths
     */
    protected function attachParticipantInvoiceFiles(
        EventSettlementParticipantPayment $payment,
        array $storedPaths,
        ?string $documentNumber = null,
    ): void {
        if ($storedPaths === [] || ! Schema::hasTable('event_documents')) {
            return;
        }

        foreach ($storedPaths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            \App\Models\EventDocument::query()->create([
                'event_id' => $this->eventId,
                'name' => 'Faktura: '.($payment->participant_name ?: ('wpłata #'.$payment->id)),
                'notes' => trim(implode(' · ', array_filter([
                    $documentNumber ? 'Nr '.$documentNumber : null,
                    'participant_payment_id='.$payment->id,
                ]))),
                'file_path' => $path,
                'original_filename' => basename($path),
                'is_invoice' => true,
                'approval_status' => 'pending',
            ]);
        }
    }

    /**
     * @return array{has_file: bool, label: ?string, url: ?string}
     */
    protected function resolveInvoiceMeta(EventSettlementParticipantPayment $payment): array
    {
        $hasNumber = filled($payment->document_number);
        $doc = null;

        if (Schema::hasTable('event_documents')) {
            $doc = \App\Models\EventDocument::query()
                ->where('event_id', $this->eventId)
                ->where('is_invoice', true)
                ->where(function ($q) use ($payment): void {
                    $q->where('notes', 'like', '%participant_payment_id='.$payment->id.'%');
                    if (filled($payment->participant_name)) {
                        $q->orWhere('name', 'like', '%'.addcslashes((string) $payment->participant_name, '%_').'%');
                    }
                })
                ->orderByDesc('id')
                ->first();
        }

        $url = null;
        if ($doc && filled($doc->file_path)) {
            $url = \Illuminate\Support\Facades\Storage::disk('public')->url($doc->file_path);
        }

        return [
            'has_file' => $doc !== null || $hasNumber,
            'label' => $doc || $hasNumber ? 'Faktura wgrana' : null,
            'url' => $url,
        ];
    }

    protected function settlement(): EventSettlement
    {
        return EventSettlement::query()
            ->where('event_id', $this->eventId)
            ->whereKey($this->settlementId)
            ->firstOrFail();
    }

    protected function findPayment(int $paymentId): EventSettlementParticipantPayment
    {
        return EventSettlementParticipantPayment::query()
            ->where('settlement_id', $this->settlementId)
            ->whereHas('settlement', fn ($query) => $query->where('event_id', $this->eventId))
            ->whereKey($paymentId)
            ->firstOrFail();
    }

    protected function notifyChanged(string $message): void
    {
        $this->dispatch('settlement-data-changed');

        Notification::make()
            ->title($message)
            ->success()
            ->send();
    }
}
