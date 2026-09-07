<?php

namespace App\Filament\Forms;

use App\Models\Contractor;
use App\Models\Currency;
use App\Models\Event;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PilotAdvanceService;
use App\Support\Tasks\OfficeTaskRecipients;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Support\Enums\ActionSize;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class EventReadinessFields
{
    /**
     * Uwagi wewnętrzne biura (ubezpieczenie: Operacje → Ubezpieczenia).
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function officeSection(): array
    {
        return [
            Forms\Components\Section::make('Biuro')
                ->icon('heroicon-o-building-office')
                ->description('Opiekun imprezy i uwagi wewnętrzne. Ubezpieczenie: Impreza → Ubezpieczenia. Pilot: Impreza → Pilot.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Select::make('office_caretaker_id')
                        ->label('Opiekun imprezy')
                        ->helperText('Opcjonalne — może zostać puste. Puste: zapytania i zadania systemowe idą do puli biura (bez osobistego przypisania). Wypełnij tylko, gdy konkretna osoba ma dostać je pierwsza (mail 24h, potem eskalacja).')
                        ->options(fn (): array => OfficeTaskRecipients::users()
                            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name])
                            ->all())
                        ->searchable()
                        ->nullable()
                        ->preload()
                        ->visible(fn (): bool => Schema::hasColumn('events', 'office_caretaker_id'))
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('check_in_moved_hint')
                        ->label('Odprawa pilota')
                        ->content(function (?Event $record): \Illuminate\Support\HtmlString {
                            if (! $record) {
                                return new \Illuminate\Support\HtmlString('Edycja odprawy: Impreza → Pilot.');
                            }

                            $url = e(\App\Filament\Resources\EventResource::getUrl('pilot', ['record' => $record]));

                            return new \Illuminate\Support\HtmlString(
                                'Edycja odprawy jest w <a href="'.$url.'" class="text-primary-600 underline font-medium">Impreza → Pilot</a>.'
                            );
                        })
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('insurance_moved_hint')
                        ->label('Ubezpieczenie')
                        ->content(function (?Event $record): \Illuminate\Support\HtmlString {
                            if (! $record) {
                                return new \Illuminate\Support\HtmlString('Polisa, gotowość i koszty NNW/KL: Impreza → Ubezpieczenia.');
                            }

                            $url = e(\App\Filament\Resources\EventResource::getUrl('day-insurances', ['record' => $record]));

                            return new \Illuminate\Support\HtmlString(
                                'Polisa, gotowość i koszty NNW/KL są w <a href="'.$url.'" class="text-primary-600 underline font-medium">Impreza → Ubezpieczenia</a>.'
                            );
                        })
                        ->columnSpanFull(),
                    EventNotesFields::officeNotes()
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * Pilot, zaliczka i uwagi — z nagłówkiem (modal / inne ekrany).
     *
     * @param  (callable(Forms\Components\Section): void)|null  $configureSection
     * @return array<int, Forms\Components\Component>
     */
    public static function pilotSection(?callable $configureSection = null): array
    {
        $section = Forms\Components\Section::make('Pilot wycieczki')
            ->icon('heroicon-o-user-circle')
            ->description('Przypisanie pilota, zaliczka i uwagi operacyjne.')
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                Forms\Components\Fieldset::make('Przypisanie')
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema(EventKeyInfoFields::pilotFields()),

                Forms\Components\Fieldset::make('Zaliczka pilota')
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->visible(fn (): bool => Schema::hasColumn('events', 'pilot_funds_paid'))
                    ->schema(self::pilotAdvanceFields()),

                EventNotesFields::pilotNotes()
                    ->columnSpanFull(),
            ]);

        if ($configureSection) {
            $configureSection($section);
        }

        return [$section];
    }

    /**
     * Schemat na stronę Impreza → Pilot (zakładki UI w Blade; grupy CSS-only, żeby stan formularza nie ginął).
     *
     * Portal / checklista / cash-desk są poza Form (nested Livewire w Placeholder psuje akcje Filament).
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function pilotPageSchema(): array
    {
        return [
            Forms\Components\Group::make([
                Forms\Components\Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Forms\Components\Group::make([
                            Forms\Components\Section::make('Odprawa')
                                ->icon('heroicon-o-clipboard-document-list')
                                ->compact()
                                ->schema(self::checkInInputComponents()),

                            Forms\Components\Section::make('Uwagi dla pilota')
                                ->icon('heroicon-o-chat-bubble-left-right')
                                ->compact()
                                ->schema([
                                    EventNotesFields::pilotNotes()->hiddenLabel(),
                                ]),
                        ])->columnSpan(1),

                        Forms\Components\Section::make('Przypisanie pilota')
                            ->icon('heroicon-o-identification')
                            ->compact()
                            ->schema(EventKeyInfoFields::pilotFields())
                            ->columnSpan(1),
                    ]),
            ])
                ->extraAttributes(['class' => 'pilot-form-tab pilot-form-tab--briefing'])
                ->columnSpanFull(),

            Forms\Components\Group::make([
                Forms\Components\Placeholder::make('pilot_cash_flow_strip')
                    ->hiddenLabel()
                    ->content(function ($livewire): \Illuminate\Support\HtmlString {
                        if (! is_object($livewire) || ! method_exists($livewire, 'pilotPageSummary')) {
                            return new \Illuminate\Support\HtmlString('');
                        }

                        /** @var array<string, mixed> $summary */
                        $summary = $livewire->pilotPageSummary();

                        return new \Illuminate\Support\HtmlString(
                            view('filament.resources.event-resource.pages.partials.pilot-cash-flow-strip', [
                                'summary' => $summary,
                            ])->render()
                        );
                    })
                    ->columnSpanFull(),

                Forms\Components\Section::make('Plan i zatwierdzenie wypłaty')
                    ->description('Krok 1–2. Po zatwierdzeniu korektę kwot robisz w rozliczeniu obok (wypłata / saldo / wymiana).')
                    ->icon('heroicon-o-banknotes')
                    ->compact()
                    ->columns(['default' => 1, 'md' => 2])
                    ->visible(fn (): bool => Schema::hasColumn('events', 'pilot_funds_paid'))
                    ->schema(self::pilotAdvanceFields()),
            ])
                ->extraAttributes(['class' => 'pilot-form-tab pilot-form-tab--cash'])
                ->columnSpanFull(),

            Forms\Components\Group::make([
                Forms\Components\Placeholder::make('pilot_settlement_status_card')
                    ->hiddenLabel()
                    ->content(function ($livewire): \Illuminate\Support\HtmlString {
                        if (! is_object($livewire) || ! method_exists($livewire, 'pilotPageSummary')) {
                            return new \Illuminate\Support\HtmlString('');
                        }

                        /** @var array<string, mixed> $summary */
                        $summary = $livewire->pilotPageSummary();

                        return new \Illuminate\Support\HtmlString(
                            view('filament.resources.event-resource.pages.partials.pilot-settlement-status-card', [
                                'summary' => $summary,
                            ])->render()
                        );
                    })
                    ->columnSpanFull(),

                Forms\Components\Section::make('Wynagrodzenie pilota')
                    ->description('Należność za prowadzenie wycieczki (dzieło / FV) — osobno od gotówki operacyjnej. Kwoty w walutach.')
                    ->icon('heroicon-o-currency-dollar')
                    ->compact()
                    ->columns(['default' => 1, 'md' => 2])
                    ->visible(fn (): bool => Schema::hasTable('pilot_fee_lines'))
                    ->schema(self::pilotFeeFields()),
            ])
                ->extraAttributes(['class' => 'pilot-form-tab pilot-form-tab--settlement'])
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function pilotFeeFields(): array
    {
        return [
            Forms\Components\Repeater::make('pilot_fee_due_lines')
                ->label('Należne wynagrodzenie')
                ->helperText('Np. 1200 PLN albo 800 PLN + 100 EUR. Forma rozliczenia (dzieło/FV) jest przy przypisaniu pilota.')
                ->schema([
                    Forms\Components\TextInput::make('amount')
                        ->label('Kwota')
                        ->numeric()
                        ->minValue(0.01)
                        ->required(),
                    Forms\Components\Select::make('currency_id')
                        ->label('Waluta')
                        ->options(fn () => Currency::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->default(fn () => Currency::query()->where('code', 'PLN')->orWhere('symbol', 'PLN')->value('id')),
                ])
                ->columns(['default' => 1, 'md' => 2])
                ->defaultItems(0)
                ->itemLabel(function (array $state): ?string {
                    $amount = $state['amount'] ?? null;
                    $currencyId = (int) ($state['currency_id'] ?? 0);
                    if ($amount === null || $amount === '' || $currencyId <= 0) {
                        return 'Nowa pozycja';
                    }

                    $currency = Currency::query()->find($currencyId);
                    $code = $currency?->code ?: $currency?->symbol ?: '';

                    return number_format((float) $amount, 2, ',', ' ').($code !== '' ? ' '.$code : '');
                })
                ->addActionLabel('Dodaj kwotę')
                ->addAction(fn (Forms\Components\Actions\Action $action) => $action
                    ->button()
                    ->color('primary')
                    ->size(ActionSize::Medium)
                    ->icon('heroicon-m-plus'))
                ->columnSpanFull()
                ->dehydrated(),

            Forms\Components\Placeholder::make('pilot_fee_paid_info')
                ->label('Wypłacone')
                ->content(function (?Event $record): \Illuminate\Support\HtmlString|string {
                    if (! $record) {
                        return '—';
                    }

                    $fee = app(\App\Services\PilotFeeService::class);
                    $paid = e($fee->formatPaidLabel($record));
                    $remaining = e($fee->formatRemainingLabel($record));

                    if ($paid === '—') {
                        return new \Illuminate\Support\HtmlString(
                            '<p class="text-sm text-gray-500">Jeszcze nie oznaczono wypłaty wynagrodzenia.</p>'
                        );
                    }

                    $remainingHtml = $fee->hasOutstandingFee($record)
                        ? '<p class="text-sm text-amber-700 dark:text-amber-400">Pozostało: <span class="font-medium">'.$remaining.'</span></p>'
                        : '<p class="text-sm text-green-700 dark:text-green-400">Wynagrodzenie wypłacone w całości.</p>';

                    return new \Illuminate\Support\HtmlString(
                        '<div class="space-y-1 text-sm">'
                        .'<p><span class="font-medium text-gray-950 dark:text-white">'.$paid.'</span></p>'
                        .$remainingHtml
                        .'</div>'
                    );
                })
                ->columnSpanFull(),

            Forms\Components\Toggle::make('pilot_fee_mark_paid')
                ->label('Oznacz wynagrodzenie jako wypłacone')
                ->helperText('Kopiuje kwoty należne jako wypłacone. Po zapisie możesz cofnąć osobną akcją.')
                ->columnSpanFull()
                ->dehydrated()
                ->disabled(fn (Forms\Get $get, ?Event $record): bool => blank($get('pilot_fee_due_lines'))
                    || (bool) ($record && app(\App\Services\PilotFeeService::class)->isFullyPaid($record))),

            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('clear_pilot_fee_paid')
                    ->label('Cofnij wypłatę wynagrodzenia')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cofnąć oznaczenie wypłaty wynagrodzenia?')
                    ->modalDescription('Linie „wypłacone” zostaną usunięte. Należne kwoty zostaną bez zmian.')
                    ->action(function ($livewire): void {
                        if (! is_object($livewire) || ! method_exists($livewire, 'clearPilotFeePaid')) {
                            return;
                        }
                        $livewire->clearPilotFeePaid();
                    })
                    ->visible(fn (?Event $record): bool => (bool) ($record
                        && app(\App\Services\PilotFeeService::class)->paidLines($record)->isNotEmpty())),
                Forms\Components\Actions\Action::make('confirm_pilot_settlement_close')
                    ->label('Potwierdź i zamknij rozliczenie')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Zamknąć rozliczenie pilota?')
                    ->modalDescription('Potwierdzasz weryfikację gotówki i wynagrodzenia. Pilot nie powinien już edytować rozliczenia jako otwartego.')
                    ->action(function ($livewire): void {
                        if (! is_object($livewire) || ! method_exists($livewire, 'confirmPilotSettlementClose')) {
                            return;
                        }
                        $livewire->confirmPilotSettlementClose();
                    })
                    ->visible(fn (?Event $record): bool => (bool) ($record
                        && ($record->latestSettlement?->status ?? 'draft') !== 'closed')),
                Forms\Components\Actions\Action::make('reopen_pilot_settlement')
                    ->label('Otwórz ponownie')
                    ->icon('heroicon-o-lock-open')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function ($livewire): void {
                        if (! is_object($livewire) || ! method_exists($livewire, 'reopenPilotSettlement')) {
                            return;
                        }
                        $livewire->reopenPilotSettlement();
                    })
                    ->visible(fn (?Event $record): bool => (bool) ($record
                        && ($record->latestSettlement?->status ?? null) === 'closed')),
            ])->columnSpanFull(),
        ];
    }

    /**
     * Dane kierowcy (bez nr rejestracyjnego i bez szczegółów podstawienia).
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function driverFields(): array
    {
        return [
            ...self::driverIdentityFields(),
            self::driverPickupSentToggle(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function driverIdentityFields(): array
    {
        if (Schema::hasColumn('events', 'driver_contractor_id')) {
            return [
                ...TypedContractorSelect::make(
                    field: 'driver_contractor_id',
                    label: 'Kierowca',
                    typeNames: ['kierowca'],
                    searchAllField: 'driver_contractor_search_all',
                    defaultTypeOnCreate: 'kierowca',
                    helperText: 'Wybierz kierowcę z kontrahentów albo dodaj nowego (typ „kierowca”).',
                    searchAllHelperText: 'Domyślnie tylko typ „kierowca”. Zaznacz, gdy kontrahent ma źle przypisany typ.',
                    afterStateUpdated: function ($state, callable $set): void {
                        if (! $state) {
                            $set('driver_name', null);
                            $set('driver_phone', null);

                            return;
                        }

                        $contractor = Contractor::query()->find($state);
                        $set('driver_name', $contractor?->name);
                        $set('driver_phone', $contractor?->phone);
                    },
                    columnSpan: 'full',
                ),
                Forms\Components\Hidden::make('driver_name')
                    ->dehydrated()
                    ->visible(fn (): bool => Schema::hasColumn('events', 'driver_name')),
                Forms\Components\Hidden::make('driver_phone')
                    ->dehydrated()
                    ->visible(fn (): bool => Schema::hasColumn('events', 'driver_phone')),
                ...TransportContractorContactsFields::make(
                    contractorField: 'driver_contractor_id',
                    prefix: 'transport_driver',
                    afterContractorCardUpdated: function (Contractor $contractor, callable $set): void {
                        if (Schema::hasColumn('events', 'driver_name')) {
                            $set('driver_name', $contractor->name);
                        }
                        if (Schema::hasColumn('events', 'driver_phone')) {
                            $set('driver_phone', $contractor->phone);
                        }
                    },
                ),
            ];
        }

        return [
            Forms\Components\TextInput::make('driver_name')
                ->label('Kierowca')
                ->maxLength(255)
                ->visible(fn (): bool => Schema::hasColumn('events', 'driver_name')),

            PhoneInput::make('driver_phone')
                ->label('Telefon kierowcy')
                ->visible(fn (): bool => Schema::hasColumn('events', 'driver_phone')),
        ];
    }

    public static function vehicleRegistrationPreview(): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('vehicle_registration_from_fleet')
            ->label('Dane pojazdu')
            ->content(function (Get $get, ?Event $record): HtmlString|string {
                $vehicleId = (int) ($get('main_fleet_vehicle_id') ?: 0);
                $vehicle = $vehicleId > 0 ? Vehicle::query()->find($vehicleId) : null;

                // Fallback: zapisany snapshot / przypisanie, gdy select jeszcze nie ma stanu.
                if (! $vehicle && $record) {
                    if (Schema::hasTable('event_vehicles')) {
                        $record->loadMissing(['eventVehicles.vehicle']);
                        $vehicle = $record->eventVehicles
                            ->sortBy(fn ($ev) => $ev->role?->value === 'main' ? 0 : 1)
                            ->first()
                            ?->vehicle;
                    }

                    if (! $vehicle && filled($record->vehicle_registration)) {
                        return (string) $record->vehicle_registration;
                    }
                }

                if (! $vehicle) {
                    return 'Wybierz pojazd floty powyżej — dane pojawią się automatycznie.';
                }

                $reg = filled($vehicle->registration_number)
                    ? (string) $vehicle->registration_number
                    : null;
                $name = trim(implode(' ', array_filter([(string) $vehicle->brand, (string) $vehicle->model])));
                $year = $vehicle->manufactureYearLabel();
                $capacity = $vehicle->capacityLabel();
                $type = $vehicle->type?->label();

                $detailParts = array_values(array_filter([
                    $name !== '' ? e($name) : null,
                    $year ? e('rocznik '.$year) : null,
                    $capacity ? e($capacity) : null,
                    $type ? e($type) : null,
                ]));

                if ($reg === null && $detailParts === []) {
                    return 'Brak danych pojazdu — uzupełnij kartę we flocie.';
                }

                $html = $reg
                    ? '<div style="font-weight:600;font-size:1.05rem">'.e($reg).'</div>'
                    : '';
                if ($detailParts !== []) {
                    $html .= '<div style="margin-top:2px;font-size:0.875rem;color:#4b5563">'
                        .implode(' · ', $detailParts)
                        .'</div>';
                }

                return new HtmlString($html);
            })
            ->helperText('Źródło: flota pojazdów. Nie edytujesz numeru ręcznie na imprezie.')
            ->visible(fn (): bool => Schema::hasColumn('events', 'vehicle_registration'));
    }

    public static function pickupPlaceDetailsField(): \FilamentTiptapEditor\TiptapEditor
    {
        return \FilamentTiptapEditor\TiptapEditor::make('pickup_place_details')
            ->label('Dokładny adres podstawienia')
            ->columnSpanFull()
            ->visible(fn (): bool => Schema::hasColumn('events', 'pickup_place_details'))
            ->helperText('Adres dla kierowcy (brama, punkt orientacyjny). Punkt startowy do kalkulacji wybierasz osobno powyżej. Godziny: sekcja „Terminy transportu”.');
    }

    public static function driverPickupSentToggle(): Forms\Components\Toggle
    {
        return Forms\Components\Toggle::make('driver_pickup_info_sent')
            ->label('Wysłano do kierowcy')
            ->helperText(function (?Event $record): string {
                if (! $record?->isDriverPickupInfoSent()) {
                    return 'Gotowość operacyjna — zaznacz ręcznie albo użyj „Wyślij do kierowcy”.';
                }

                $by = $record->driverPickupInfoSentByUser?->name ?? '—';
                $when = $record->driver_pickup_info_sent_at?->format('d.m.Y H:i') ?? '—';

                return "Oznaczone jako wysłane: {$when} · {$by}";
            })
            ->dehydrated()
            ->columnSpanFull()
            ->visible(fn (): bool => Schema::hasColumn('events', 'driver_pickup_info_sent_at'));
    }

    /**
     * @deprecated Użyj officeSection() i pilotSection().
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(): array
    {
        return [
            ...self::officeSection(),
            ...self::pilotSection(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function modalSchema(): array
    {
        return [
            Forms\Components\Fieldset::make('Odprawa')
                ->columns(['default' => 1, 'md' => 2])
                ->columnSpanFull()
                ->schema(self::checkInInputComponents()),

            Forms\Components\Fieldset::make('Zaliczka pilota')
                ->columns(['default' => 1, 'md' => 2])
                ->columnSpanFull()
                ->visible(fn (): bool => Schema::hasColumn('events', 'pilot_funds_paid'))
                ->schema(self::pilotAdvanceFields()),

            Forms\Components\Fieldset::make('Kierowca — podstawienie')
                ->columns(['default' => 1, 'md' => 2])
                ->columnSpanFull()
                ->visible(fn (?Event $record): bool => $record?->requiresDriverPickupInfo() ?? true)
                ->schema([
                    ...EventTransportFields::transportTimeFields(),
                    ...self::driverIdentityFields(),
                    self::vehicleRegistrationPreview(),
                    self::pickupPlaceDetailsField(),
                    self::driverPickupSentToggle(),
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formState(Event $event): array
    {
        return [
            'check_in_status' => $event->check_in_status ?? 'pending',
            'check_in_notes' => $event->check_in_notes,
            'pilot_advance_planned_amount' => $event->pilot_advance_planned_amount,
            'pilot_funds_paid' => (bool) $event->pilot_funds_paid,
            'insurance_policy_number' => $event->insurance_policy_number,
            'insurance_status' => $event->insurance_status ?: 'pending',
            'insurance_payment_status' => $event->insurance_payment_status ?: 'pending',
            'insurance_amount' => $event->insurance_amount,
            'insurance_paid_at' => $event->insurance_paid_at,
            'insurance_document_path' => Event::insuranceFileUploadState($event->insurance_document_path),
            'insurance_insured_list_path' => Event::insuranceFileUploadState($event->insurance_insured_list_path),
            'insurance_terms' => $event->insurance_terms,
            'substitution_time' => $event->substitution_time,
            'departure_time' => $event->departure_time,
            'return_time' => $event->return_time,
            'driver_name' => $event->driver_name,
            'driver_phone' => $event->driver_phone,
            'driver_contractor_id' => $event->driver_contractor_id,
            'vehicle_registration' => $event->vehicle_registration,
            'pickup_place_details' => $event->pickup_place_details,
            'driver_pickup_info_sent' => $event->isDriverPickupInfoSent(),
        ];
    }

    public static function persist(Event $event, array $data): void
    {
        $payload = [];

        if (Schema::hasColumn('events', 'check_in_status')) {
            $payload['check_in_status'] = $data['check_in_status'] ?? 'pending';
            $payload['check_in_notes'] = $data['check_in_notes'] ?? null;
        }

        if (Schema::hasColumn('events', 'pilot_advance_planned_amount') && ! $event->pilot_funds_paid) {
            $planned = $data['pilot_advance_planned_amount'] ?? null;
            $planned = $planned !== null && $planned !== '' ? round((float) $planned, 2) : null;

            $payload['pilot_advance_planned_amount'] = $planned;

            if ($planned !== null && $planned > 0) {
                if (! $event->pilot_advance_planned_at) {
                    $payload['pilot_advance_planned_at'] = now();
                    $payload['pilot_advance_planned_by'] = Auth::id();
                }
            } elseif ($planned === null) {
                $payload['pilot_advance_planned_at'] = null;
                $payload['pilot_advance_planned_by'] = null;
            }
        }

        if (Schema::hasColumn('events', 'driver_contractor_id') && array_key_exists('driver_contractor_id', $data)) {
            $contractorId = filled($data['driver_contractor_id'] ?? null) ? (int) $data['driver_contractor_id'] : null;
            $payload['driver_contractor_id'] = $contractorId;

            if ($contractorId) {
                $contractor = Contractor::query()->find($contractorId);
                if (Schema::hasColumn('events', 'driver_name')) {
                    $payload['driver_name'] = $contractor?->name;
                }
                if (Schema::hasColumn('events', 'driver_phone')) {
                    $payload['driver_phone'] = $contractor?->phone;
                }
            } else {
                if (Schema::hasColumn('events', 'driver_name') && array_key_exists('driver_name', $data)) {
                    $payload['driver_name'] = $data['driver_name'];
                }
                if (Schema::hasColumn('events', 'driver_phone') && array_key_exists('driver_phone', $data)) {
                    $payload['driver_phone'] = $data['driver_phone'];
                }
            }
        } else {
            foreach (['driver_name', 'driver_phone'] as $field) {
                if (Schema::hasColumn('events', $field) && array_key_exists($field, $data)) {
                    $payload[$field] = $data[$field];
                }
            }
        }

        foreach (['substitution_time', 'departure_time', 'return_time', 'pickup_place_details'] as $field) {
            if (Schema::hasColumn('events', $field) && array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (Schema::hasColumn('events', 'driver_pickup_info_sent_at') && array_key_exists('driver_pickup_info_sent', $data)) {
            $sent = (bool) ($data['driver_pickup_info_sent'] ?? false);

            if ($sent && ! $event->driver_pickup_info_sent_at) {
                $payload['driver_pickup_info_sent_at'] = now();
                $payload['driver_pickup_info_sent_by'] = Auth::id();
            } elseif (! $sent) {
                $payload['driver_pickup_info_sent_at'] = null;
                $payload['driver_pickup_info_sent_by'] = null;
            }
        }

        if ($payload !== []) {
            $event->update($payload);
            $event->refresh();
        }

        if (Schema::hasColumn('events', 'pilot_funds_paid')
            && ! $event->pilot_funds_paid
            && ! empty($data['pilot_funds_paid'])) {
            app(PilotAdvanceService::class)->approvePayment($event->fresh());
        }

        $event->updateInsuranceFromFormData($data);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function checkInFields(): array
    {
        return [
            Forms\Components\Fieldset::make('Odprawa')
                ->columns(['default' => 1, 'md' => 2])
                ->columnSpanFull()
                ->schema(self::checkInInputComponents()),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function checkInInputComponents(): array
    {
        return [
            Forms\Components\Select::make('check_in_status')
                ->label('Status odprawy')
                ->options(Event::getCheckInStatusOptions())
                ->default('pending')
                ->required()
                ->visible(fn (): bool => Schema::hasColumn('events', 'check_in_status')),

            Forms\Components\Textarea::make('check_in_notes')
                ->label('Uwagi do odprawy')
                ->rows(2)
                ->columnSpanFull()
                ->visible(fn (): bool => Schema::hasColumn('events', 'check_in_notes')),
        ];
    }

    /**
     * Sekcja ubezpieczenia do samodzielnego użycia (np. na liście uczestników).
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function insuranceSection(): array
    {
        return self::insuranceFields();
    }

    /**
     * Schema modala „Polisa imprezy” (Operacje → Ubezpieczenia, umowy / kontrakty / uczestnicy).
     * Bez widoczności zależnej od $record relacji — akcja sama decyduje o show.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function insuranceModalSchema(): array
    {
        return [
            Forms\Components\Fieldset::make('Ubezpieczenie')
                ->columns(['default' => 1, 'md' => 2])
                ->columnSpanFull()
                ->schema(self::insuranceInputComponents()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function insuranceFormState(Event $event): array
    {
        return [
            'insurance_policy_number' => $event->insurance_policy_number,
            'insurance_status' => $event->insurance_status ?: 'pending',
            'insurance_payment_status' => $event->insurance_payment_status ?: 'pending',
            'insurance_amount' => $event->insurance_amount,
            'insurance_paid_at' => $event->insurance_paid_at,
            'insurance_document_path' => Event::insuranceFileUploadState($event->insurance_document_path),
            'insurance_insured_list_path' => Event::insuranceFileUploadState($event->insurance_insured_list_path),
            'insurance_terms' => $event->insurance_terms,
        ];
    }

    public static function persistInsurance(Event $event, array $data): void
    {
        $event->updateInsuranceFromFormData($data);
        $event->refresh();
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function insuranceFields(): array
    {
        return [
            Forms\Components\Fieldset::make('Ubezpieczenie')
                ->columns(['default' => 1, 'md' => 2])
                ->columnSpanFull()
                ->schema(self::insuranceInputComponents()),
        ];
    }

    /**
     * Pola workflow polisy — publiczne dla wspólnego modala Operacje → Ubezpieczenia.
     *
     * FileUpload Filepond trzyma DOM między otwarciami modala — `__insurance_upload_key`
     * z fillForm wymusza remount (wire:key), żeby nie pokazywał pliku z poprzedniego wiersza.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function insuranceInputComponents(): array
    {
        $uploadKey = fn (Get $get): string => (string) ($get('__insurance_upload_key') ?: 'default');

        return [
            Forms\Components\Hidden::make('__insurance_upload_key')
                ->dehydrated(false),

            Forms\Components\TextInput::make('insurance_policy_number')
                ->label('Nr polisy')
                ->maxLength(255),

            Forms\Components\TextInput::make('insurance_amount')
                ->label('Kwota')
                ->numeric()
                ->suffix('PLN')
                ->nullable()
                ->helperText('Kwota operacyjna polisy (może różnić się od wyliczenia NNW w ofercie). Płatność rejestrujesz w kolumnie „Płatność” / panelu Płatności.'),

            Forms\Components\FileUpload::make('insurance_document_path')
                ->label('Plik polisy')
                ->helperText('Widoczny dla pilota (panel Dokumenty + pakiet PDF) oraz w Finanse → Koszty / Dok. rozliczenia.')
                ->disk('public')
                ->directory('event-insurance')
                ->maxFiles(1)
                ->downloadable()
                ->openable()
                ->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg', 'image/webp'])
                ->id(fn (Get $get): string => 'insurance_document_path_'.$uploadKey($get))
                ->extraFieldWrapperAttributes(fn (Get $get): array => [
                    'wire:key' => 'insurance-document-'.$uploadKey($get),
                ])
                ->columnSpanFull()
                ->nullable(),

            Forms\Components\FileUpload::make('insurance_insured_list_path')
                ->label('Oryginalna lista ubezpieczonych')
                ->helperText('Lista z towarzystwa ubezpieczeniowego. Pilot ma do niej dostęp w panelu Dokumenty i w pakiecie PDF.')
                ->disk('public')
                ->directory('event-insurance')
                ->maxFiles(1)
                ->downloadable()
                ->openable()
                ->acceptedFileTypes([
                    'application/pdf',
                    'image/png',
                    'image/jpeg',
                    'image/webp',
                    'text/csv',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ])
                ->visible(fn (): bool => Schema::hasColumn('events', 'insurance_insured_list_path')
                    || Schema::hasTable('event_insurance_policies'))
                ->id(fn (Get $get): string => 'insurance_insured_list_path_'.$uploadKey($get))
                ->extraFieldWrapperAttributes(fn (Get $get): array => [
                    'wire:key' => 'insurance-list-'.$uploadKey($get),
                ])
                ->columnSpanFull()
                ->nullable(),

            Forms\Components\Textarea::make('insurance_terms')
                ->label('Warunki ubezpieczenia')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function insuranceFormStateFromPolicy(?\App\Models\EventInsurancePolicy $policy, ?Event $event = null): array
    {
        if ($policy) {
            return $policy->toFormState();
        }

        if ($event) {
            return self::insuranceFormState($event);
        }

        return [
            'insurance_policy_number' => null,
            'insurance_status' => 'pending',
            'insurance_payment_status' => 'pending',
            'insurance_amount' => null,
            'insurance_paid_at' => null,
            'insurance_document_path' => null,
            'insurance_insured_list_path' => null,
            'insurance_terms' => null,
            '__insurance_upload_key' => 'empty',
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function pilotAdvanceFields(): array
    {
        $hasAdvanceLines = Schema::hasTable('pilot_advance_lines');

        $plannedFields = $hasAdvanceLines
            ? [
                Forms\Components\Repeater::make('pilot_advance_planned_lines')
                    ->label('Planowane zaliczki')
                    ->helperText('Dodaj jedną lub więcej zaliczek (np. 1000 PLN i 50 EUR). Przed zatwierdzeniem wypłaty możesz edytować i usuwać pozycje.')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Kwota')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),
                        Forms\Components\Select::make('currency_id')
                            ->label('Waluta')
                            ->options(fn () => Currency::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->default(fn () => Currency::query()->where('code', 'PLN')->orWhere('symbol', 'PLN')->value('id')),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->defaultItems(0)
                    ->itemLabel(function (array $state): ?string {
                        $amount = $state['amount'] ?? null;
                        $currencyId = (int) ($state['currency_id'] ?? 0);
                        if ($amount === null || $amount === '' || $currencyId <= 0) {
                            return 'Nowa zaliczka';
                        }

                        $currency = Currency::query()->find($currencyId);
                        $code = $currency?->code ?: $currency?->symbol ?: '';

                        return number_format((float) $amount, 2, ',', ' ').($code !== '' ? ' '.$code : '');
                    })
                    ->addActionLabel('Dodaj zaliczkę')
                    ->addAction(fn (Forms\Components\Actions\Action $action) => $action
                        ->button()
                        ->color('primary')
                        ->size(ActionSize::Medium)
                        ->icon('heroicon-m-plus'))
                    ->deletable()
                    ->deleteAction(fn (Forms\Components\Actions\Action $action) => $action
                        ->label('Usuń zaliczkę')
                        ->button()
                        ->outlined()
                        ->color('danger')
                        ->size(ActionSize::Small)
                        ->icon('heroicon-m-trash'))
                    ->reorderable(false)
                    ->columnSpanFull()
                    ->visible(fn (): bool => Schema::hasColumn('events', 'pilot_advance_planned_amount'))
                    ->disabled(fn (?Event $record): bool => (bool) ($record?->pilot_funds_paid)),
            ]
            : [
                Forms\Components\TextInput::make('pilot_advance_planned_amount')
                    ->label('Planowana zaliczka')
                    ->numeric()
                    ->suffix('PLN')
                    ->minValue(0)
                    ->nullable()
                    ->helperText('Planowana gotówka — widoczna w finansach, bez zasilenia salda pilota.')
                    ->visible(fn (): bool => Schema::hasColumn('events', 'pilot_advance_planned_amount'))
                    ->disabled(fn (?Event $record): bool => (bool) ($record?->pilot_funds_paid)),
            ];

        return [
            ...$plannedFields,

            Forms\Components\Placeholder::make('pilot_advance_planned_meta')
                ->label('Plan zapisany')
                ->content(function (?Event $record): string {
                    if (! $record?->pilot_advance_planned_at) {
                        return '—';
                    }

                    $by = $record->pilotAdvancePlannedByUser?->name ?? 'system';

                    return $record->pilot_advance_planned_at->format('d.m.Y H:i').' · '.$by;
                })
                ->visible(fn (?Event $record): bool => $record && filled($record->pilot_advance_planned_amount)),

            Forms\Components\TextInput::make('pilot_advance_paid_amount')
                ->label('Kwota rzeczywista wypłaty (pierwsza linia / PLN)')
                ->numeric()
                ->minValue(0)
                ->nullable()
                ->live()
                ->visible(fn (Forms\Get $get, ?Event $record): bool => Schema::hasColumn('events', 'pilot_advance_paid_amount')
                    && ! $hasAdvanceLines
                    && filled($get('pilot_advance_planned_amount'))
                    && ! (bool) ($record?->pilot_funds_paid))
                ->default(fn (?Event $record, Forms\Get $get) => $get('pilot_advance_planned_amount')),

            Forms\Components\Select::make('pilot_advance_paid_currency_id')
                ->label('Waluta wypłaty')
                ->options(fn () => \App\Models\Currency::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->default(fn () => \App\Models\Currency::query()->where('symbol', 'PLN')->orWhere('code', 'PLN')->value('id'))
                ->visible(fn (Forms\Get $get, ?Event $record): bool => Schema::hasColumn('events', 'pilot_advance_paid_currency_id')
                    && ! $hasAdvanceLines
                    && filled($get('pilot_advance_planned_amount'))
                    && ! (bool) ($record?->pilot_funds_paid)),

            Forms\Components\Textarea::make('pilot_advance_paid_comment')
                ->label('Komentarz do wypłaty')
                ->rows(2)
                ->nullable()
                ->visible(fn (Forms\Get $get, ?Event $record): bool => Schema::hasColumn('events', 'pilot_advance_paid_comment')
                    && (
                        $hasAdvanceLines
                            ? filled($get('pilot_advance_planned_lines'))
                            : filled($get('pilot_advance_planned_amount'))
                    )
                    && ! (bool) ($record?->pilot_funds_paid)),

            Forms\Components\Toggle::make('pilot_funds_paid')
                ->label('Wypłacona — zatwierdź rzeczywistą wypłatę')
                ->helperText('Oznacza fizyczną wypłatę (Wypłacona) i zasila saldo gotówki pilota we wszystkich zaplanowanych walutach. Po zatwierdzeniu cofasz osobną akcją „Cofnij wypłatę”.')
                ->columnSpanFull()
                ->disabled(fn (Forms\Get $get, ?Event $record): bool => (
                    $hasAdvanceLines
                        ? blank($get('pilot_advance_planned_lines'))
                        : blank($get('pilot_advance_planned_amount'))
                ) || (bool) ($record?->pilot_funds_paid)),

            Forms\Components\Placeholder::make('pilot_funds_paid_info')
                ->label('Wypłacona')
                ->content(function (?Event $record): \Illuminate\Support\HtmlString|string {
                    if (! $record?->pilot_funds_paid) {
                        return '—';
                    }

                    $at = $record->pilot_funds_paid_at?->format('d.m.Y H:i') ?? '—';
                    $by = e($record->pilotFundsPaidByUser?->name ?? '—');
                    $payoutLabel = e(app(PilotAdvanceService::class)->formatOfficePayoutLabel($record));
                    $comment = filled($record->pilot_advance_paid_comment)
                        ? ' · '.e($record->pilot_advance_paid_comment)
                        : '';

                    return new \Illuminate\Support\HtmlString(
                        '<div class="space-y-1 text-sm">'
                        .'<p><span class="font-medium text-gray-950 dark:text-white">'.$payoutLabel.'</span></p>'
                        .'<p class="text-gray-500 dark:text-gray-400">'.$at.' · '.$by.$comment.'</p>'
                        .'<p class="text-xs text-gray-500 dark:text-gray-400">'
                        .'Pomyłka w kwocie? Edytuj lub usuń zaliczkę w rozliczeniu poniżej — albo cofnij wypłatę i popraw plan.'
                        .'</p>'
                        .'</div>'
                    );
                })
                ->columnSpanFull()
                ->visible(fn (?Event $record): bool => (bool) ($record?->pilot_funds_paid)),

            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('goto_pilot_cash_desk')
                    ->label('Edytuj / dopłać / dodaj zaliczkę')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->action(function ($livewire): void {
                        if (is_object($livewire) && method_exists($livewire, 'scrollToPilotCashDesk')) {
                            $livewire->scrollToPilotCashDesk();
                        }
                    })
                    ->visible(fn (?Event $record, $livewire): bool => (bool) ($record?->pilot_funds_paid)
                        && is_a($livewire, \App\Filament\Resources\EventResource\Pages\ManageEventPilot::class)),

                Forms\Components\Actions\Action::make('revoke_pilot_funds_paid')
                    ->label('Cofnij wypłatę')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cofnąć wypłatę gotówki?')
                    ->modalDescription('Usunie oznaczenie „Wypłacona” i zeruje kwoty „Od biura”. Planowane zaliczki zostaną. Jeśli są wymiany walut, najpierw je usuń lub popraw.')
                    ->modalSubmitActionLabel('Cofnij wypłatę')
                    ->visible(fn (?Event $record): bool => (bool) ($record?->pilot_funds_paid))
                    ->action(function (?Event $record, $livewire): void {
                        if (method_exists($livewire, 'revokePilotOfficePayout')) {
                            $livewire->revokePilotOfficePayout();

                            return;
                        }

                        if (! $record) {
                            return;
                        }

                        try {
                            app(PilotAdvanceService::class)->clearAllOfficeCashPayouts($record);
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->title('Nie można cofnąć wypłaty')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Cofnięto wypłatę gotówki')
                            ->success()
                            ->send();
                    }),
            ])
                ->columnSpanFull()
                ->visible(fn (?Event $record): bool => (bool) ($record?->pilot_funds_paid)),
        ];
    }
}
