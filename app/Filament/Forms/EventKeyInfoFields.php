<?php

namespace App\Filament\Forms;

use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\User;
use App\Support\PilotIdentityValidation;
use Filament\Forms;
use Filament\Forms\Get;

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
            Forms\Components\View::make('filament.components.event-readiness-inline')
                ->hiddenOn('create')
                ->columnSpanFull(),

            Forms\Components\Section::make('Impreza')
                ->icon('heroicon-o-calendar-days')
                ->description('Nazwa, kod identyfikacyjny i termin wyjazdu.')
                ->columns(3)
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
                        ->columns(3)
                        ->columnSpanFull(),

                    Forms\Components\Group::make([
                        ...EventTransportFields::transportTimeFields(),
                    ])
                        ->columns(3)
                        ->columnSpanFull(),

                ]),
        ];
    }

    /**
     * Szablon, status, uczestnicy i zamawiający.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function basicSection(): array
    {
        return [
            Forms\Components\Section::make('Podstawowe informacje')
                ->icon('heroicon-o-document-text')
                ->description('Szablon, status sprzedaży, skład grupy i dane zamawiającego.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('event_template_id')
                        ->label('Szablon imprezy')
                        ->options(EventTemplate::where('deleted_at', null)->pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('Bez szablonu (impreza czysta)')
                        ->nullable()
                        ->reactive()
                        ->afterStateUpdated(fn (callable $get, callable $set, ?Event $record) => \App\Filament\Resources\EventResource::refreshTotalCostFromTemplateState($set, $get, $record))
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

                        Forms\Components\TextInput::make('participant_count')
                            ->label('Liczba uczestników')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (callable $get, callable $set, $livewire): void {
                                $record = (isset($livewire->record) && $livewire->record instanceof Event)
                                    ? $livewire->record
                                    : null;

                                if ($record) {
                                    \App\Filament\Resources\EventResource::syncGratisCountFromQtyVariant($set, $get, $record);
                                }

                                \App\Filament\Resources\EventResource::refreshTotalCostFromTemplateState($set, $get, $record);
                            })
                            ->required(),

                        Forms\Components\TextInput::make('gratis_count')
                            ->label(\App\Support\EventParticipantGroupLabels::GRATIS)
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->dehydrated()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (callable $get, callable $set, ?Event $record) => \App\Filament\Resources\EventResource::refreshTotalCostFromTemplateState($set, $get, $record))
                            ->helperText('Osoby jadące w grupie bez opłaty za siebie. Uwzględniane w kalkulacji kosztów i zapisywane w wariancie ilościowym grupy.'),
                    ])
                        ->columns(3)
                        ->columnSpanFull(),

                    EventNotesFields::dietInfo(),

                    Forms\Components\Group::make([
                        Forms\Components\Select::make('start_place_id')
                            ->label('Miejsce startu (podstawienia)')
                            ->options(fn (callable $get) => \App\Models\Place::startingPlaceSelectOptionsForTemplate(
                                (int) ($get('event_template_id') ?? 0) ?: null,
                                (int) ($get('start_place_id') ?? 0) ?: null,
                            ))
                            ->searchable()
                            ->nullable()
                            ->reactive()
                            ->afterStateUpdated(function (callable $get, callable $set, $livewire, ?Event $record): void {
                                $templateId = (int) ($get('event_template_id') ?? $record?->event_template_id ?? 0);
                                $startPlaceId = (int) ($get('start_place_id') ?? 0);
                                $currentTransfer = (float) ($get('transfer_km') ?? 0);

                                if (class_exists(\App\Filament\Resources\EventResource::class)) {
                                    $set('transfer_km', \App\Filament\Resources\EventResource::resolveTransferKmFromTemplateState(
                                        $templateId,
                                        $startPlaceId,
                                        $currentTransfer
                                    ));
                                    \App\Filament\Resources\EventResource::refreshTotalCostFromTemplateState($set, $get, $record);
                                }

                                if (method_exists($livewire, 'dispatch')) {
                                    $livewire->dispatch('event-price-table-refresh');
                                }
                            })
                            ->helperText(fn (callable $get): string => filled($get('event_template_id'))
                                ? 'Punkty startowe dostępne dla wybranego szablonu.'
                                : 'Tylko punkty startowe (podstawienia autokaru) — wymagane do obliczenia transferu i ceny z szablonu.'),

                        Forms\Components\TextInput::make('program_km')
                            ->label('Kilometry programu')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (callable $get, callable $set, $livewire, ?Event $record): void {
                                if (class_exists(\App\Filament\Resources\EventResource::class)) {
                                    \App\Filament\Resources\EventResource::refreshTotalCostFromTemplateState($set, $get, $record);
                                }
                                if (method_exists($livewire, 'dispatch')) {
                                    $livewire->dispatch('event-price-table-refresh');
                                }
                            }),

                        Forms\Components\TextInput::make('transfer_km')
                            ->label('Kilometry transferu')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (callable $get, callable $set, $livewire, ?Event $record): void {
                                if (class_exists(\App\Filament\Resources\EventResource::class)) {
                                    \App\Filament\Resources\EventResource::refreshTotalCostFromTemplateState($set, $get, $record);
                                }
                                if (method_exists($livewire, 'dispatch')) {
                                    $livewire->dispatch('event-price-table-refresh');
                                }
                            }),
                    ])
                        ->columns(3)
                        ->columnSpanFull(),

                    ...\App\Filament\Forms\EventPricePerPersonFields::manualPriceFields(),

                    Forms\Components\Fieldset::make('Zamawiający')
                        ->columns(2)
                        ->columnSpanFull()
                        ->schema([
                            EventOrderingPartyFields::orderingPartiesRepeater(),
                            Forms\Components\TextInput::make('client_name')
                                ->label('Główny zamawiający (nazwa)')
                                ->required()
                                ->maxLength(255)
                                ->helperText('Uzupełniane z pierwszego zamawiającego. Można edytować ręcznie.'),
                            Forms\Components\TextInput::make('client_email')
                                ->label('Email głównego zamawiającego')
                                ->email()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('client_phone')
                                ->label('Telefon głównego zamawiającego')
                                ->maxLength(\App\Support\PhoneValidation::MAX_LENGTH)
                                ->rules(\App\Support\PhoneValidation::optionalRules()),
                        ]),

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
     * @deprecated Pola przeniesione bezpośrednio do identitySection
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function scheduleFields(): array
    {
        return [];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function pilotFields(): array
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

                        return;
                    }

                    $user = User::query()->find($state);
                    $set('pilot_birth_date', $user?->birth_date?->format('Y-m-d'));
                    $set('pilot_pesel', $user?->pesel);
                    $set('pilot_phone', $user?->phone);
                })
                ->helperText('Odpowiedzialny za imprezę. Telefon, data urodzenia i PESEL zapisują się w profilu użytkownika.'),

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
