<?php

namespace App\Filament\Forms;

use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\User;
use App\Services\PilotContractorAssignmentService;
use App\Support\EventBusSeatCapacity;
use App\Support\PilotIdentityValidation;
use Filament\Forms;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Schema;

class EventKeyInfoFields
{
    /**
     * Nazwa, kod i termin wyjazdu.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function identitySection(): array
    {
        return [
            // Status operacyjny jest w headerze edit-event.blade.php — bez duplikatu w formularzu.

            Forms\Components\Section::make('Impreza')
                ->icon('heroicon-o-calendar-days')
                ->description('Nazwa, kod identyfikacyjny i termin wyjazdu.')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nazwa imprezy')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->helperText('Nazwa widoczna dla klienta i w dokumentach.'),

                    Forms\Components\TextInput::make('code')
                        ->label('Kod imprezy')
                        ->readOnly()
                        ->disabled()
                        ->dehydrated(false)
                        ->hiddenOn('create')
                        ->columnSpanFull()
                        ->helperText('Unikalny kod identyfikacyjny — generowany automatycznie.'),

                    Forms\Components\Group::make([
                        Forms\Components\TextInput::make('duration_days')
                            ->label('Liczba dni')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, callable $set): void {
                                if (empty($get('start_date'))) {
                                    return;
                                }

                                $days = max(1, (int) ($state ?? 1));
                                $start = \Carbon\Carbon::parse($get('start_date'));
                                $set('end_date', $start->copy()->addDays($days - 1)->toDateString());
                            })
                            ->helperText('Obliczana z dat lub kopiowana z szablonu.'),

                        Forms\Components\DatePicker::make('start_date')
                            ->label('Data rozpoczęcia')
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, callable $set): void {
                                if (empty($state)) {
                                    return;
                                }

                                $start = \Carbon\Carbon::parse($state);
                                $endDate = $get('end_date');

                                if (! empty($endDate)) {
                                    $end = \Carbon\Carbon::parse($endDate);
                                    if ($end->lt($start)) {
                                        $set('end_date', $start->toDateString());
                                        $set('duration_days', 1);

                                        return;
                                    }

                                    $set('duration_days', max(1, $start->diffInDays($end) + 1));

                                    return;
                                }

                                $duration = max(1, (int) ($get('duration_days') ?? 1));
                                $set('end_date', $start->copy()->addDays($duration - 1)->toDateString());
                            }),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('Data zakończenia')
                            ->native(false)
                            ->minDate(fn (Get $get) => $get('start_date'))
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, callable $set): void {
                                if (empty($state) || empty($get('start_date'))) {
                                    return;
                                }

                                $start = \Carbon\Carbon::parse($get('start_date'));
                                $end = \Carbon\Carbon::parse($state);

                                if ($end->lt($start)) {
                                    $set('end_date', $start->toDateString());
                                    $set('duration_days', 1);

                                    return;
                                }

                                $set('duration_days', max(1, $start->diffInDays($end) + 1));
                            }),
                    ])
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                        ->columnSpanFull(),

                    Forms\Components\Group::make([
                        ...EventTransportFields::transportTimeFields(),
                    ])
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                        ->columnSpanFull(),

                ]),
        ];
    }

    /**
     * Liczba uczestników + gratis — wspólne dla Create i Edit.
     *
     * @param  (callable(callable, callable): void)|null  $onUpdated
     * @return array<int, Forms\Components\Component>
     */
    public static function participantFields(?callable $onUpdated = null): array
    {
        // Create przekazuje onUpdated z silnikiem szablonu.
        // Edit: bez refreshTotalCostFromTemplateState — SSoT to EventCostCalculator w Placeholderze.
        $refresh = $onUpdated ?? static function (callable $get, callable $set): void {};

        return [
            Forms\Components\TextInput::make('participant_count')
                ->label('Liczba uczestników')
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->live(onBlur: true)
                ->afterStateUpdated(function (callable $get, callable $set, $livewire) use ($refresh): void {
                    if (isset($livewire->record) && $livewire->record instanceof Event) {
                        \App\Filament\Resources\EventResource::syncGratisCountFromQtyVariant($set, $get, $livewire->record);
                    }

                    $refresh($get, $set);
                    if (is_object($livewire) && method_exists($livewire, 'dispatch')) {
                        $livewire->dispatch('event-price-table-refresh');
                    }
                })
                ->required(),

            Forms\Components\TextInput::make('gratis_count')
                ->label(\App\Support\EventParticipantGroupLabels::GRATIS)
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->dehydrated()
                ->live(onBlur: true)
                ->afterStateUpdated(function (callable $get, callable $set, $livewire) use ($refresh): void {
                    $refresh($get, $set);
                    if (is_object($livewire) && method_exists($livewire, 'dispatch')) {
                        $livewire->dispatch('event-price-table-refresh');
                    }
                })
                ->helperText('Osoby jadące w grupie bez opłaty za siebie. Uwzględniane w kalkulacji kosztów i zapisywane w wariancie ilościowym grupy.'),

            Forms\Components\Placeholder::make('bus_seat_capacity_warning')
                ->hiddenLabel()
                ->visible(fn (callable $get, ?Event $record): bool => EventBusSeatCapacity::resolveMessage($get, $record) !== null)
                ->content(fn (callable $get, ?Event $record) => EventBusSeatCapacity::warningHtml($get, $record) ?? '')
                ->columnSpanFull(),
        ];
    }

    /**
     * Miejsce startu + km programu/transferu — wspólne dla Create i Edit.
     *
     * @param  (callable(callable, callable): void)|null  $onUpdated
     * @return array<int, Forms\Components\Component>
     */
    public static function placeAndDistanceFields(?callable $onUpdated = null, bool $includeProgramStartPlace = false): array
    {
        $refresh = $onUpdated ?? function (callable $get, callable $set): void {
            \App\Filament\Resources\EventResource::refreshTotalCostFromTemplateState($set, $get);
        };

        $fields = [
            Forms\Components\Select::make('start_place_id')
                ->label('Miejsce startu (podstawienia)')
                ->options(fn (callable $get, ?Event $record) => \App\Models\Place::startingPlaceSelectOptionsForTemplate(
                    (int) ($get('event_template_id') ?? $record?->event_template_id ?? 0) ?: null,
                    (int) ($get('start_place_id') ?? $record?->start_place_id ?? 0) ?: null,
                ))
                ->searchable()
                ->nullable()
                ->reactive()
                ->afterStateUpdated(function (callable $get, callable $set, $livewire) use ($refresh): void {
                    $templateId = (int) ($get('event_template_id') ?? 0);
                    $startPlaceId = (int) ($get('start_place_id') ?? 0);
                    $currentTransfer = (float) ($get('transfer_km') ?? 0);

                    if ($templateId > 0) {
                        $set('transfer_km', \App\Filament\Resources\EventResource::resolveTransferKmFromTemplateState(
                            $templateId,
                            $startPlaceId,
                            $currentTransfer
                        ));
                    } else {
                        $programStartPlaceId = (int) ($get('program_start_place_id') ?? 0);
                        if ($programStartPlaceId > 0 && $startPlaceId > 0) {
                            $d1 = (float) (\App\Models\PlaceDistance::query()
                                ->where('from_place_id', $startPlaceId)
                                ->where('to_place_id', $programStartPlaceId)
                                ->value('distance_km') ?? 0);
                            $set('transfer_km', $d1 * 2);
                        }
                    }

                    $refresh($get, $set);

                    if (method_exists($livewire, 'dispatch')) {
                        $livewire->dispatch('event-price-table-refresh');
                    }
                })
                ->helperText(fn (callable $get, ?Event $record): string => filled($get('event_template_id') ?? $record?->event_template_id)
                    ? 'Punkty startowe dostępne dla wybranego szablonu.'
                    : 'Tylko punkty startowe (podstawienia autokaru) — wymagane do obliczenia transferu i ceny z szablonu.'),
        ];

        if ($includeProgramStartPlace) {
            $fields[] = Forms\Components\Select::make('program_start_place_id')
                ->label('Początek programu')
                ->options(fn () => \App\Models\Place::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->nullable()
                ->dehydrated(fn (): bool => \Illuminate\Support\Facades\Schema::hasColumn('events', 'program_start_place_id'))
                ->reactive()
                ->visible(fn (callable $get): bool => empty($get('event_template_id'))
                    || \Illuminate\Support\Facades\Schema::hasColumn('events', 'program_start_place_id'))
                ->afterStateUpdated(function (callable $get, callable $set): void {
                    $startPlaceId = (int) ($get('start_place_id') ?? 0);
                    $programStartPlaceId = (int) ($get('program_start_place_id') ?? 0);
                    if ($programStartPlaceId > 0 && $startPlaceId > 0) {
                        $d1 = (float) (\App\Models\PlaceDistance::query()
                            ->where('from_place_id', $startPlaceId)
                            ->where('to_place_id', $programStartPlaceId)
                            ->value('distance_km') ?? 0);
                        $set('transfer_km', $d1 * 2);
                    }
                })
                ->helperText(fn (callable $get): string => empty($get('event_template_id'))
                    ? 'Miejsce rozpoczęcia programu — zapisywane i widoczne w Transporcie; służy też do przeliczenia transferu (x2).'
                    : 'Z szablonu: miejsce startu programu. Możesz skorygować — wartość trafia do Transporcie.');
        }

        $fields[] = Forms\Components\TextInput::make('program_km')
            ->label('Kilometry programu')
            ->numeric()
            ->minValue(0)
            ->default(0)
            ->live(onBlur: true)
            ->afterStateUpdated(function (callable $get, callable $set, $livewire) use ($refresh): void {
                $refresh($get, $set);
                if (method_exists($livewire, 'dispatch')) {
                    $livewire->dispatch('event-price-table-refresh');
                }
            });

        $fields[] = Forms\Components\TextInput::make('transfer_km')
            ->label('Kilometry transferu')
            ->numeric()
            ->minValue(0)
            ->default(0)
            ->live(onBlur: true)
            ->afterStateUpdated(function (callable $get, callable $set, $livewire) use ($refresh): void {
                $refresh($get, $set);
                if (method_exists($livewire, 'dispatch')) {
                    $livewire->dispatch('event-price-table-refresh');
                }
            });

        return $fields;
    }

    public static function basicSection(): array
    {
        return [
            Forms\Components\Section::make('Podstawowe informacje')
                ->icon('heroicon-o-document-text')
                ->description('Szablon, status sprzedaży, skład grupy i dane zamawiającego.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Select::make('event_template_id')
                        ->label('Szablon imprezy')
                        ->options(EventTemplate::where('deleted_at', null)->pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('Bez szablonu (impreza czysta)')
                        ->nullable()
                        ->reactive()
                        ->afterStateUpdated(fn (callable $get, callable $set) => \App\Filament\Resources\EventResource::refreshTotalCostFromTemplateState($set, $get))
                        ->disabledOn('edit')
                        ->dehydrated()
                        ->hintAction(
                            Forms\Components\Actions\Action::make('view_template')
                                ->label('Podgląd szablonu')
                                ->icon('heroicon-m-eye')
                                ->url(fn ($state) => $state ? \App\Filament\Resources\EventTemplateResource::getUrl('edit', ['record' => $state]) : null)
                                ->openUrlInNewTab()
                                ->visible(fn (string $operation, $state): bool => $operation === 'edit' && filled($state))
                        )
                        ->helperText(fn (string $operation): ?string => $operation === 'create' ? 'Szablon programu i kalkulacji.' : 'Brak możliwości zmiany szablonu po utworzeniu imprezy.')
                        ->columnSpanFull(),

                    Forms\Components\Group::make([
                        Forms\Components\Select::make('status')
                            ->label('Status imprezy')
                            ->options(Event::getStatusOptions())
                            ->default(Event::STATUS_INQUIRY)
                            ->required(),

                        ...self::participantFields(),
                    ])
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                        ->columnSpanFull(),

                    EventNotesFields::dietInfo(),

                    Forms\Components\Group::make([
                        ...self::placeAndDistanceFields(),
                    ])
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                        ->columnSpanFull(),

                    ...\App\Filament\Forms\EventPricePerPersonFields::manualPriceFields(),

                    Forms\Components\Section::make('Zamawiający')
                        ->icon('heroicon-o-user-circle')
                        ->description('Ten sam układ co przy zakładaniu imprezy — karta wybranego klienta.')
                        ->columnSpanFull()
                        ->schema(EventOrderingPartyFields::clientLookupFields()),

                    EventNotesFields::generalNotes()
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @deprecated Użyj identitySection() i basicSection().
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(): array
    {
        return [
            ...self::identitySection(),
            ...self::basicSection(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function pilotFields(): array
    {
        if (! Schema::hasColumn('events', 'pilot_contractor_id')) {
            return self::legacyPilotFields();
        }

        $assignmentService = app(PilotContractorAssignmentService::class);

        return [
            ...TypedContractorSelect::make(
                field: 'pilot_contractor_id',
                label: 'Pilot / opiekun',
                typeNames: ['pilot'],
                searchAllField: 'pilot_contractor_search_all',
                defaultTypeOnCreate: 'pilot',
                helperText: 'Wybierz pilota z kontrahentów albo dodaj nowego (typ „pilot”).',
                searchAllHelperText: 'Domyślnie tylko typ „pilot”. Zaznacz, gdy kontrahent ma źle przypisany typ.',
                afterStateUpdated: function ($state, callable $set) use ($assignmentService): void {
                    $assignmentService->applyContactFieldsToForm(
                        filled($state) ? (int) $state : null,
                        $set,
                    );
                },
                columnSpan: 'full',
            ),

            ...TransportContractorContactsFields::make(
                contractorField: 'pilot_contractor_id',
                prefix: 'pilot',
                afterContractorCardUpdated: function (Contractor $contractor, callable $set) use ($assignmentService): void {
                    $assignmentService->applyContactFieldsToForm((int) $contractor->getKey(), $set);
                },
            ),

            Forms\Components\DatePicker::make('pilot_birth_date')
                ->label('Data urodzenia pilota')
                ->displayFormat('d.m.Y')
                ->native(false)
                ->nullable()
                ->visible(fn (Get $get): bool => filled($get('pilot_contractor_id'))),

            Forms\Components\TextInput::make('pilot_pesel')
                ->label('PESEL pilota')
                ->maxLength(11)
                ->nullable()
                ->rules(PilotIdentityValidation::optionalPeselRules())
                ->visible(fn (Get $get): bool => filled($get('pilot_contractor_id')))
                ->helperText('Opcjonalnie — zapis w karcie kontrahenta.'),

            Forms\Components\Placeholder::make('pilot_portal_account')
                ->label('Konto panelu pilota')
                ->content(function (Get $get) use ($assignmentService): string {
                    $contractorId = (int) ($get('pilot_contractor_id') ?? 0);

                    if ($contractorId <= 0) {
                        return '—';
                    }

                    $contractor = Contractor::query()->find($contractorId);

                    return $assignmentService->assignedUserLabel($contractor);
                })
                ->visible(fn (Get $get): bool => filled($get('pilot_contractor_id')))
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function legacyPilotFields(): array
    {
        return [
            Forms\Components\Select::make('assigned_to')
                ->label('Pilot / opiekun')
                ->options(User::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->nullable()
                ->live()
                ->afterStateUpdated(function ($state, callable $set): void {
                    if (! $state) {
                        $set('pilot_birth_date', null);
                        $set('pilot_pesel', null);
                        $set('pilot_phone', null);
                        $set('pilot_email', null);

                        return;
                    }

                    $user = User::query()->find($state);
                    $set('pilot_birth_date', $user?->birth_date?->format('Y-m-d'));
                    $set('pilot_pesel', $user?->pesel);
                    $set('pilot_phone', $user?->phone);
                    $set('pilot_email', $user?->email);
                })
                ->helperText('Odpowiedzialny za imprezę. Telefon i dane osobowe zapisują się w profilu użytkownika.'),

            Forms\Components\TextInput::make('pilot_email')
                ->label('E-mail pilota')
                ->email()
                ->disabled()
                ->dehydrated(false)
                ->visible(fn (Get $get): bool => filled($get('assigned_to'))),

            PhoneInput::make('pilot_phone')
                ->label('Telefon pilota')
                ->visible(fn (Get $get): bool => filled($get('assigned_to'))),

            Forms\Components\DatePicker::make('pilot_birth_date')
                ->label('Data urodzenia pilota')
                ->displayFormat('d.m.Y')
                ->native(false)
                ->nullable()
                ->visible(fn (Get $get): bool => filled($get('assigned_to'))),

            Forms\Components\TextInput::make('pilot_pesel')
                ->label('PESEL pilota')
                ->maxLength(11)
                ->nullable()
                ->rules(PilotIdentityValidation::optionalPeselRules())
                ->visible(fn (Get $get): bool => filled($get('assigned_to')))
                ->helperText('Opcjonalnie — zapis w profilu użytkownika.'),
        ];
    }
}
