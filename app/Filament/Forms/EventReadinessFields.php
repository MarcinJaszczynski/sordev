<?php

namespace App\Filament\Forms;

use App\Models\Event;
use App\Services\PilotAdvanceService;
use Filament\Forms;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class EventReadinessFields
{
    /**
     * Odprawa, ubezpieczenie i uwagi wewnętrzne biura.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function officeSection(): array
    {
        return [
            Forms\Components\Section::make('Biuro')
                ->icon('heroicon-o-building-office')
                ->description('Odprawa, ubezpieczenie i uwagi wewnętrzne dla pracowników biura.')
                ->columns(2)
                ->schema([
                    ...self::checkInFields(),
                    ...self::insuranceFields(),
                    EventNotesFields::officeNotes()
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * Pilot, zaliczka i uwagi dla opiekuna wycieczki.
     *
     * @param  (callable(Forms\Components\Section): void)|null  $configureSection
     * @return array<int, Forms\Components\Component>
     */
    public static function pilotSection(?callable $configureSection = null): array
    {
        $section = Forms\Components\Section::make('Pilot')
            ->icon('heroicon-o-user-circle')
            ->description('Przypisanie pilota, zaliczka gotówkowa i uwagi operacyjne.')
            ->columns(2)
            ->schema([
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

                Forms\Components\Fieldset::make('Przypisanie')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema(EventKeyInfoFields::pilotFields()),

                Forms\Components\Fieldset::make('Zaliczka pilota')
                    ->columns(2)
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
     * Pola kierowcy i podstawienia — używane w formularzu transportu i w modalu gotowości.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function driverFields(): array
    {
        return [
            Forms\Components\TimePicker::make('departure_time')
                ->label('Godzina podstawienia')
                ->seconds(false)
                ->native(false)
                ->nullable()
                ->visible(fn (): bool => Schema::hasColumn('events', 'departure_time'))
                ->helperText('Godzina zbiórki / podstawienia autokaru.'),

            Forms\Components\TimePicker::make('return_time')
                ->label('Godzina powrotu')
                ->seconds(false)
                ->native(false)
                ->nullable()
                ->visible(fn (): bool => Schema::hasColumn('events', 'return_time'))
                ->helperText('Godzina planowanego powrotu autokaru.'),

            Forms\Components\TextInput::make('driver_name')
                ->label('Kierowca')
                ->maxLength(255)
                ->visible(fn (): bool => Schema::hasColumn('events', 'driver_name')),

            PhoneInput::make('driver_phone')
                ->label('Telefon kierowcy')
                ->visible(fn (): bool => Schema::hasColumn('events', 'driver_phone')),

            Forms\Components\TextInput::make('vehicle_registration')
                ->label('Nr rejestracyjny')
                ->maxLength(32)
                ->visible(fn (): bool => Schema::hasColumn('events', 'vehicle_registration')),

            \FilamentTiptapEditor\TiptapEditor::make('pickup_place_details')
                ->label('Szczegóły miejsca podstawienia')
                
                ->columnSpanFull()
                ->visible(fn (): bool => Schema::hasColumn('events', 'pickup_place_details'))
                ->helperText('Np. dokładny adres, brama, punkt orientacyjny.'),

            Forms\Components\Toggle::make('driver_pickup_info_sent')
                ->label('Wysłano kierowcy informację o podstawieniu')
                ->helperText('Zaznacz po przekazaniu kierowcy godziny, miejsca i danych kontaktowych.')
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
                ->columns(2)
                ->columnSpanFull()
                ->schema(self::checkInFields()),

            Forms\Components\Fieldset::make('Zaliczka pilota')
                ->columns(2)
                ->columnSpanFull()
                ->visible(fn (): bool => Schema::hasColumn('events', 'pilot_funds_paid'))
                ->schema(self::pilotAdvanceFields()),

            ...self::insuranceFields(),

            Forms\Components\Placeholder::make('insurance_not_required')
                ->label('Ubezpieczenie')
                ->content('Brak wymogu ubezpieczenia dla tej imprezy.')
                ->columnSpanFull()
                ->visible(fn (?Event $record): bool => $record && ! $record->requiresInsuranceWorkflow()),

            Forms\Components\Fieldset::make('Kierowca — podstawienie')
                ->columns(2)
                ->columnSpanFull()
                ->visible(fn (?Event $record): bool => $record?->requiresDriverPickupInfo() ?? true)
                ->schema(self::driverFields()),
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
            'insurance_terms' => $event->insurance_terms,
            'departure_time' => $event->departure_time,
            'driver_name' => $event->driver_name,
            'driver_phone' => $event->driver_phone,
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

        foreach (['departure_time', 'driver_name', 'driver_phone', 'vehicle_registration', 'pickup_place_details'] as $field) {
            if (Schema::hasColumn('events', $field) && array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (Schema::hasColumn('events', 'driver_pickup_info_sent_at')) {
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

        if ($event->requiresInsuranceWorkflow()) {
            $event->updateInsuranceFromFormData($data);
        }
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function checkInFields(): array
    {
        return [
            Forms\Components\Fieldset::make('Odprawa')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
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
                ]),
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
            'insurance_terms' => $event->insurance_terms,
        ];
    }

    public static function persistInsurance(Event $event, array $data): void
    {
        if ($event->requiresInsuranceWorkflow()) {
            $event->updateInsuranceFromFormData($data);
            $event->refresh();
        }
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function insuranceFields(): array
    {
        return [
            Forms\Components\Fieldset::make('Ubezpieczenie')
                ->columns(2)
                ->columnSpanFull()
                ->visible(fn (?Event $record): bool => $record?->requiresInsuranceWorkflow() ?? false)
                ->schema([
                    Forms\Components\TextInput::make('insurance_policy_number')
                        ->label('Nr polisy')
                        ->maxLength(255),

                    Forms\Components\Select::make('insurance_status')
                        ->label('Status ubezpieczenia')
                        ->options(Event::getInsuranceStatusOptions())
                        ->default('pending')
                        ->required(),

                    Forms\Components\Select::make('insurance_payment_status')
                        ->label('Status płatności')
                        ->options(Event::getInsurancePaymentStatusOptions())
                        ->default('pending')
                        ->required(),

                    Forms\Components\TextInput::make('insurance_amount')
                        ->label('Kwota')
                        ->numeric()
                        ->suffix('PLN')
                        ->nullable(),

                    Forms\Components\DateTimePicker::make('insurance_paid_at')
                        ->label('Data płatności')
                        ->native(false)
                        ->nullable(),

                    Forms\Components\FileUpload::make('insurance_document_path')
                        ->label('Dokument')
                        ->disk('public')
                        ->directory('event-insurance')
                        ->downloadable()
                        ->openable()
                        ->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg', 'image/webp'])
                        ->columnSpanFull()
                        ->nullable(),

                    Forms\Components\Textarea::make('insurance_terms')
                        ->label('Warunki ubezpieczenia')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Placeholder::make('insurance_not_required')
                ->label('Ubezpieczenie')
                ->content('Brak wymogu ubezpieczenia dla tej imprezy.')
                ->columnSpanFull()
                ->visible(fn (?Event $record): bool => $record && ! $record->requiresInsuranceWorkflow()),
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
                    ->label('Planowana zaliczka')
                    ->helperText('Możesz zaplanować kilka walut naraz, np. 1000 PLN i 50 EUR.')
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
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Dodaj walutę')
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
                    ->helperText('Krok 1 — plan widoczny w finansach, bez zasilenia salda pilota.')
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
                ->label('Zatwierdź rzeczywistą wypłatę')
                ->helperText('Krok 2 — oznacza fizyczną wypłatę i zasila saldo gotówki pilota we wszystkich zaplanowanych walutach.')
                ->columnSpanFull()
                ->disabled(fn (Forms\Get $get, ?Event $record): bool => (
                    $hasAdvanceLines
                        ? blank($get('pilot_advance_planned_lines'))
                        : blank($get('pilot_advance_planned_amount'))
                ) || (bool) ($record?->pilot_funds_paid)),

            Forms\Components\Placeholder::make('pilot_funds_paid_info')
                ->label('Wypłacono')
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
