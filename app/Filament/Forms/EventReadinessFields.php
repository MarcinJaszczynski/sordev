<?php

namespace App\Filament\Forms;

use App\Models\Contractor;
use App\Models\Event;
use App\Models\User;
use App\Services\PilotAdvanceService;
use App\Support\Tasks\OfficeTaskRecipients;
use Filament\Forms;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

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
                ->description('Opiekun imprezy i uwagi wewnętrzne. Ubezpieczenie: Operacje → Ubezpieczenia. Odprawa pilota: Operacje → Pilot.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Select::make('office_caretaker_id')
                        ->label('Opiekun imprezy')
                        ->helperText('Opcjonalnie. Zapytania z portalu klienta/pilota trafiają najpierw do opiekuna; po 24h bez odpowiedzi — do całego biura.')
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
                                return new \Illuminate\Support\HtmlString('Edycja odprawy: Impreza → Operacje → Pilot.');
                            }

                            $url = e(\App\Filament\Resources\EventResource::getUrl('pilot', ['record' => $record]));

                            return new \Illuminate\Support\HtmlString(
                                'Edycja odprawy jest w <a href="'.$url.'" class="text-primary-600 underline font-medium">Operacje → Pilot</a>.'
                            );
                        })
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('insurance_moved_hint')
                        ->label('Ubezpieczenie')
                        ->content(function (?Event $record): \Illuminate\Support\HtmlString {
                            if (! $record) {
                                return new \Illuminate\Support\HtmlString('Polisa, gotowość i koszty NNW/KL: Impreza → Operacje → Ubezpieczenia.');
                            }

                            $url = e(\App\Filament\Resources\EventResource::getUrl('day-insurances', ['record' => $record]));

                            return new \Illuminate\Support\HtmlString(
                                'Polisa, gotowość i koszty NNW/KL są w <a href="'.$url.'" class="text-primary-600 underline font-medium">Operacje → Ubezpieczenia</a>.'
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
     * Schemat na stronę Operacje → Pilot (bez powtórzenia „Pilot wycieczki” w H1/sekcji).
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function pilotPageSchema(): array
    {
        return [
            Forms\Components\Placeholder::make('pilot_portal_settings_toolbar')
                ->hiddenLabel()
                ->content(fn (?Event $record): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                    $record
                        ? \Illuminate\Support\Facades\Blade::render(
                            '@livewire(\'pilot-portal-settings-toolbar\', [\'eventId\' => '.$record->getKey().'], key(\'pilot-portal-toolbar-'.$record->getKey().'\'))'
                        )
                        : ''
                ))
                ->columnSpanFull(),

            Forms\Components\Placeholder::make('pilot_participant_count')
                ->label('Uczestnicy')
                ->content(function (?Event $record): string {
                    if (! $record) {
                        return '—';
                    }

                    $paying = max(0, (int) ($record->participant_count ?? 0));
                    $gratis = max(0, (int) $record->resolveGratisCountForParticipantCount($paying ?: null));

                    return $gratis > 0
                        ? sprintf('%d + %d (płacący + opiekunowie)', $paying, $gratis)
                        : (string) $paying;
                })
                ->columnSpanFull(),

            Forms\Components\Section::make('Odprawa')
                ->columns(['default' => 1, 'md' => 2])
                ->schema(self::checkInInputComponents()),

            Forms\Components\Section::make('Przypisanie')
                ->columns(['default' => 1, 'md' => 2])
                ->schema(EventKeyInfoFields::pilotFields()),

            Forms\Components\Section::make('Gotówka — Planowana / Wypłacona')
                ->description('Planowana zaliczka (krok 1) vs rzeczywista wypłata (krok 2). Sumy gotówki pilota poniżej w sekcji Rozliczenie.')
                ->columns(['default' => 1, 'md' => 2])
                ->visible(fn (): bool => Schema::hasColumn('events', 'pilot_funds_paid'))
                ->schema(self::pilotAdvanceFields()),

            Forms\Components\Section::make('Uwagi dla pilota')
                ->schema([
                    EventNotesFields::pilotNotes()->hiddenLabel(),
                ]),
        ];
    }

    /**
     * Dane kierowcy i miejsca podstawienia (bez godzin — godziny tylko w EventTransportFields).
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function driverFields(): array
    {
        $driverSelect = Schema::hasColumn('events', 'driver_contractor_id')
            ? [
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
            ]
            : [
                Forms\Components\TextInput::make('driver_name')
                    ->label('Kierowca')
                    ->maxLength(255)
                    ->visible(fn (): bool => Schema::hasColumn('events', 'driver_name')),

                PhoneInput::make('driver_phone')
                    ->label('Telefon kierowcy')
                    ->visible(fn (): bool => Schema::hasColumn('events', 'driver_phone')),
            ];

        return [
            ...$driverSelect,

            Forms\Components\TextInput::make('vehicle_registration')
                ->label('Nr rejestracyjny')
                ->maxLength(32)
                ->visible(fn (): bool => Schema::hasColumn('events', 'vehicle_registration')),

            \FilamentTiptapEditor\TiptapEditor::make('pickup_place_details')
                ->label('Szczegóły miejsca podstawienia')
                ->columnSpanFull()
                ->visible(fn (): bool => Schema::hasColumn('events', 'pickup_place_details'))
                ->helperText('Np. dokładny adres, brama, punkt orientacyjny. Godziny podstawienia/odjazdu/powrotu są w sekcji Impreza.'),

            Forms\Components\Toggle::make('driver_pickup_info_sent')
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
                ->visible(fn (): bool => Schema::hasColumn('events', 'driver_pickup_info_sent_at')),
        ];
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
                    ...self::driverFields(),
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
            'insurance_document_path' => $event->insurance_document_path,
            'insurance_insured_list_path' => $event->insurance_insured_list_path,
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

        foreach (['substitution_time', 'departure_time', 'return_time', 'vehicle_registration', 'pickup_place_details'] as $field) {
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
     * Schema modala „Ubezpieczenie imprezy” (umowy / kontrakty / uczestnicy).
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
            'insurance_document_path' => $event->insurance_document_path,
            'insurance_insured_list_path' => $event->insurance_insured_list_path,
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
     * Pola workflow polisy (jedna definicja — EditEvent, modal uczestników, umowy, kontrakty).
     *
     * @return array<int, Forms\Components\Component>
     */
    protected static function insuranceInputComponents(): array
    {
        return [
            Forms\Components\TextInput::make('insurance_policy_number')
                ->label('Nr polisy')
                ->maxLength(255),

            Forms\Components\Select::make('insurance_status')
                ->label('Status ubezpieczenia')
                ->options(Event::getInsuranceStatusOptions())
                ->default('pending')
                ->required()
                ->helperText('Ustaw „Gotowe”, gdy polisa jest domknięta — wtedy gotowość imprezy pokazuje OK (Operacje → Ubezpieczenia).'),

            Forms\Components\Select::make('insurance_payment_status')
                ->label('Status płatności')
                ->options(Event::getInsurancePaymentStatusOptions())
                ->default('pending')
                ->required(),

            Forms\Components\TextInput::make('insurance_amount')
                ->label('Kwota')
                ->numeric()
                ->suffix('PLN')
                ->nullable()
                ->helperText('Kwota operacyjna polisy (może różnić się od wyliczenia NNW w ofercie).'),

            Forms\Components\DatePicker::make('insurance_paid_at')
                ->label('Data płatności')
                ->native(false)
                ->nullable(),

            Forms\Components\FileUpload::make('insurance_document_path')
                ->label('Plik polisy')
                ->helperText('Widoczny dla pilota (panel Dokumenty + pakiet PDF) oraz w Finanse → Koszty / Dok. rozliczenia.')
                ->disk('public')
                ->directory('event-insurance')
                ->downloadable()
                ->openable()
                ->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg', 'image/webp'])
                ->columnSpanFull()
                ->nullable(),

            Forms\Components\FileUpload::make('insurance_insured_list_path')
                ->label('Oryginalna lista ubezpieczonych')
                ->helperText('Lista z towarzystwa ubezpieczeniowego. Pilot ma do niej dostęp w panelu Dokumenty i w pakiecie PDF.')
                ->disk('public')
                ->directory('event-insurance')
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
                ->visible(fn (): bool => Schema::hasColumn('events', 'insurance_insured_list_path'))
                ->columnSpanFull()
                ->nullable(),

            Forms\Components\Textarea::make('insurance_terms')
                ->label('Warunki ubezpieczenia')
                ->rows(3)
                ->columnSpanFull(),
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
                    ->label('Planowana')
                    ->helperText('Planowana gotówka u pilota — możesz zaplanować kilka walut, np. 1000 PLN i 50 EUR.')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Kwota')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),
                        Forms\Components\Select::make('currency_id')
                            ->label('Waluta')
                            ->options(fn () => \App\Models\Currency::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->default(fn () => \App\Models\Currency::query()->where('code', 'PLN')->orWhere('symbol', 'PLN')->value('id')),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->defaultItems(0)
                    ->addActionLabel('Dodaj walutę')
                    ->columnSpanFull()
                    ->visible(fn (): bool => Schema::hasColumn('events', 'pilot_advance_planned_amount'))
                    ->disabled(fn (?Event $record): bool => (bool) ($record?->pilot_funds_paid)),
            ]
            : [
                Forms\Components\TextInput::make('pilot_advance_planned_amount')
                    ->label('Planowana')
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
                ->helperText('Oznacza fizyczną wypłatę (Wypłacona) i zasila saldo gotówki pilota we wszystkich zaplanowanych walutach.')
                ->columnSpanFull()
                ->disabled(fn (Forms\Get $get, ?Event $record): bool => (
                    $hasAdvanceLines
                        ? blank($get('pilot_advance_planned_lines'))
                        : blank($get('pilot_advance_planned_amount'))
                ) || (bool) ($record?->pilot_funds_paid)),

            Forms\Components\Placeholder::make('pilot_funds_paid_info')
                ->label('Wypłacona')
                ->content(function (?Event $record): string {
                    if (! $record?->pilot_funds_paid) {
                        return '—';
                    }

                    $at = $record->pilot_funds_paid_at?->format('d.m.Y H:i') ?? '—';
                    $by = $record->pilotFundsPaidByUser?->name ?? '—';
                    $currency = $record->pilotAdvancePaidCurrency?->symbol ?? $record->pilotAdvancePaidCurrency?->code ?? 'PLN';
                    $amountValue = (float) ($record->pilot_advance_paid_amount ?? $record->pilot_advance_planned_amount ?? 0);
                    $amount = $amountValue > 0
                        ? \App\Support\MoneyFormatter::format($amountValue, $currency)
                        : '';
                    $comment = filled($record->pilot_advance_paid_comment)
                        ? ' · '.$record->pilot_advance_paid_comment
                        : '';

                    return trim($at.' · '.$by.($amount !== '' ? ' · '.$amount : '').$comment);
                })
                ->visible(fn (?Event $record): bool => (bool) ($record?->pilot_funds_paid)),
        ];
    }
}
