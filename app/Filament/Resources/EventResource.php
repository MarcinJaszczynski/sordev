<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\RelationManagers\TasksRelationManager as SharedTasksRelationManager;
use App\Filament\Resources\EventResource\Pages;
use App\Filament\Resources\EventResource\RelationManagers;
use App\Filament\Resources\EventResource\Traits\SearchContractorTrait;
use App\Models\Bus;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Markup;
use App\Models\Place;
use App\Models\PlaceDistance;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EventResource extends Resource
{
    use SearchContractorTrait;

    protected static ?string $model = Event::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Imprezy';
    protected static ?string $navigationLabel = 'Imprezy';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Podsumowanie finansowe')
                    ->icon('heroicon-o-banknotes')
                    ->collapsible()
                    ->compact()
                    ->columns(4)
                    ->hidden(fn (string $operation) => $operation !== 'edit')
                    ->schema([
                        Forms\Components\Placeholder::make('fs_calc_cost')
                            ->label('Cena z kalkulacji')
                            ->helperText('Obliczona na podstawie szablonu, km i liczby uczestników')
                            ->content(function ($record, callable $get): string {
                                if (!$record) {
                                    return '—';
                                }

                                $participantCount = max(1, (int) ($get('participant_count') ?? $record->participant_count ?? 1));

                                try {
                                    // Identyczna kalkulacja jak w zakładce Cennik (EventPriceTable)
                                    $widget = app(\App\Filament\Resources\EventResource\Widgets\EventPriceTable::class);
                                    $widget->record = $record;
                                    $widget->loadCalculations();

                                    $plnData   = $widget->detailedCalculations[$participantCount]['PLN'] ?? null;
                                    $totalCost = $plnData ? round((float) ($plnData['total'] ?? 0), 2) : 0.0;
                                    $perPerson = $participantCount > 0 ? $totalCost / $participantCount : 0;

                                    return 'SUMA KOŃCOWA dla PLN: ' . number_format($totalCost, 2, '.', ',') . ' PLN' . "\n" .
                                           'Cena za osobę (uczestnik): ' . number_format($perPerson, 2, '.', ',') . ' PLN';
                                } catch (\Throwable $e) {
                                    return 'Brak danych kalkulacji';
                                }
                            })
                            ->extraAttributes(['class' => 'whitespace-pre-line']),

                        Forms\Components\Placeholder::make('fs_planned_cost')
                            ->label('Do zapłaty przez biuro')
                            ->helperText('Z aktywnego rozliczenia — po rezerwacjach i ustaleniach')
                            ->content(function ($record): string {
                                if (!$record) {
                                    return '—';
                                }
                                try {
                                    $s = $record->settlements()
                                        ->whereIn('status', ['draft', 'active', 'pilot_settled'])
                                        ->latest('id')->first();
                                    if ($s && $s->planned_cost_pln !== null) {
                                        return number_format((float) $s->planned_cost_pln, 2, ',', ' ') . ' PLN';
                                    }
                                } catch (\Throwable) {}
                                return '— (brak rozliczenia)';
                            }),

                        Forms\Components\Placeholder::make('fs_actual_cost')
                            ->label('Już zapłacono przez biuro')
                            ->helperText('Suma kwot faktycznie przelanych do wykonawców')
                            ->content(function ($record): string {
                                if (!$record) {
                                    return '—';
                                }
                                try {
                                    $s = $record->settlements()
                                        ->whereIn('status', ['draft', 'active', 'pilot_settled'])
                                        ->latest('id')->first();
                                    if ($s && $s->actual_cost_pln !== null) {
                                        return number_format((float) $s->actual_cost_pln, 2, ',', ' ') . ' PLN';
                                    }
                                } catch (\Throwable) {}
                                return '— (brak rozliczenia)';
                            }),

                        Forms\Components\Placeholder::make('fs_clients_paid')
                            ->label('Wpłaty klientów')
                            ->helperText('Suma wpłat ze wszystkich umów tej imprezy')
                            ->content(function ($record): string {
                                if (!$record) {
                                    return '—';
                                }
                                try {
                                    if ($record->agreements()->doesntExist()) {
                                        return '— (brak umów)';
                                    }
                                    $paid = $record->agreements()->sum('amount_paid');
                                    return number_format((float) $paid, 2, ',', ' ') . ' PLN';
                                } catch (\Throwable) {}
                                return '—';
                            }),
                    ]),

                Forms\Components\Section::make('Podstawowe informacje')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Kod imprezy')
                            ->readOnly()
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Unikalny kod identyfikacyjny imprezy.'),
                        Forms\Components\Select::make('event_template_id')
                            ->label('Szablon imprezy')
                            ->options(EventTemplate::where('deleted_at', null)->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Bez szablonu (impreza czysta)')
                            ->nullable()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $get, callable $set) => static::refreshTotalCostFromTemplateState($set, $get))
                            ->helperText('Wybierz szablon, na podstawie którego zostanie utworzona impreza')
                            ->columnSpanFull(),
                        
                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa imprezy')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Wprowadź nazwę imprezy dla klienta'),
                        
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(Event::getStatusOptions())
                            ->default(Event::STATUS_INQUIRY)
                            ->required(),
                    ]),
                
                Forms\Components\Section::make('Informacje o kliencie')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('customer_lookup_id')
                            ->label('Wyszukaj zamawiającego')
                            ->searchable()
                            ->preload()
                            ->dehydrated(false)
                            ->options(fn (): array => static::getContractorOptions()->toArray())
                            ->getSearchResultsUsing(fn (string $search): array => static::getContractorOptions($search)->toArray())
                            ->getOptionLabelUsing(function ($value): ?string {
                                if (! $value) {
                                    return null;
                                }

                                $contractor = Contractor::find($value);

                                if (! $contractor) {
                                    return null;
                                }

                                return $contractor->name . ' (' . ($contractor->city ?? 'brak miasta') . ')';
                            })
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                if (! $state) {
                                    return;
                                }

                                $contractor = Contractor::find($state);
                                if (! $contractor) {
                                    return;
                                }

                                $contractorData = static::mapContractorToEventData($contractor);
                                $set('client_name', $contractorData['client_name']);
                                $set('client_email', $contractorData['client_email']);
                                $set('client_phone', $contractorData['client_phone']);
                            })
                            ->helperText('To pole służy do szybkiego podstawienia danych zamawiającego.'),

                        Forms\Components\TextInput::make('client_name')
                            ->label('Zamawiający')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('client_email')
                            ->label('Email klienta')
                            ->email()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('client_phone')
                            ->label('Telefon klienta')
                            ->tel()
                            ->maxLength(20),
                    ]),
                
                Forms\Components\Section::make('Szczegóły imprezy')
                    ->columns(3)
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Data rozpoczęcia')
                            ->required()
                            ->native(false)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $get, callable $set): void {
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
                            ->after('start_date')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $get, callable $set): void {
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

                        Forms\Components\TextInput::make('duration_days')
                            ->label('Liczba dni')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $get, callable $set): void {
                                if (empty($get('start_date'))) {
                                    return;
                                }

                                $days = max(1, (int) ($state ?? 1));
                                $start = \Carbon\Carbon::parse($get('start_date'));
                                $set('end_date', $start->copy()->addDays($days - 1)->toDateString());
                            })
                            ->helperText('Obliczana automatycznie na podstawie dat lub kopiowana z szablonu'),
                        
                        Forms\Components\TextInput::make('participant_count')
                            ->label('Liczba uczestników')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->reactive()
                            ->afterStateUpdated(fn (callable $get, callable $set) => static::refreshTotalCostFromTemplateState($set, $get))
                            ->required(),

                        Forms\Components\TextInput::make('gratis_count')
                            ->label('Liczba gratisów')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(fn (callable $get, callable $set) => static::refreshTotalCostFromTemplateState($set, $get))
                            ->helperText('Pole pomocnicze do kalkulacji ceny (nie jest zapisywane w events).'),
                        
                        Forms\Components\TextInput::make('total_cost')
                            ->label('Cena z kalkulacji (PLN)')
                            ->numeric()
                            ->suffix('PLN')
                            ->default(0)
                            ->readOnly()
                            ->hidden(fn (string $operation) => $operation === 'edit')
                            ->helperText('Obliczany automatycznie na podstawie szablonu i danych imprezy.'),
                        
                        Forms\Components\Select::make('assigned_to')
                            ->label('Pilot / opiekun')
                            ->options(User::pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->helperText('Wybór pilota/opiekuna odpowiedzialnego za imprezę.'),
                    ]),

                Forms\Components\Section::make('Transport i logistyka')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('contractor_id')
                            ->label('Wykonawca (kontrahent)')
                            ->options(Contractor::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->helperText('Podmiot realizujący usługę/wykonanie.'),

                        Forms\Components\TextInput::make('transfer_km')
                            ->label('Kilometry transferu')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Kopiowane z szablonu, można edytować'),

                        Forms\Components\TextInput::make('program_km')
                            ->label('Kilometry programu')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Kopiowane z szablonu, można edytować'),

                        Forms\Components\Select::make('bus_id')
                            ->label('Autokar')
                            ->options(Bus::pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->helperText('Kopiowany z szablonu, można zmienić'),

                        Forms\Components\Select::make('start_place_id')
                            ->label('Miejsce podstawienia')
                            ->options(Place::pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->reactive()
                            ->afterStateUpdated(function (callable $get, callable $set): void {
                                $templateId = (int) ($get('event_template_id') ?? 0);
                                $startPlaceId = (int) ($get('start_place_id') ?? 0);
                                $currentTransfer = (float) ($get('transfer_km') ?? 0);

                                $set('transfer_km', static::resolveTransferKmFromTemplateState(
                                    $templateId,
                                    $startPlaceId,
                                    $currentTransfer
                                ));

                                static::refreshTotalCostFromTemplateState($set, $get);
                            })
                            ->helperText('Wybierz miejsce wyjazdu dla tej imprezy'),

                        Forms\Components\TimePicker::make('departure_time')
                            ->label('Godzina podstawienia')
                            ->seconds(false)
                            ->native(false)
                            ->nullable()
                            ->visible(fn (): bool => Schema::hasColumn('events', 'departure_time'))
                            ->helperText('Opcjonalna godzina zbiórki/podstawienia autokaru.'),

                        Forms\Components\TextInput::make('transport_company_name')
                            ->label('Firma transportowa')
                            ->maxLength(255)
                            ->visible(fn (): bool => Schema::hasColumn('events', 'transport_company_name')),

                        Forms\Components\TextInput::make('driver_name')
                            ->label('Kierowca')
                            ->maxLength(255)
                            ->visible(fn (): bool => Schema::hasColumn('events', 'driver_name')),

                        Forms\Components\TextInput::make('driver_phone')
                            ->label('Telefon kierowcy')
                            ->tel()
                            ->maxLength(32)
                            ->visible(fn (): bool => Schema::hasColumn('events', 'driver_phone')),

                        Forms\Components\TextInput::make('vehicle_registration')
                            ->label('Nr rejestracyjny')
                            ->maxLength(32)
                            ->visible(fn (): bool => Schema::hasColumn('events', 'vehicle_registration')),

                        Forms\Components\RichEditor::make('pickup_place_details')
                            ->label('Dodatkowe miejsce podstawienia')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike', 'link', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'h2', 'h3', 'color', 'highlight', 'undo', 'redo'
                            ])
                            ->columnSpanFull()
                            ->visible(fn (): bool => Schema::hasColumn('events', 'pickup_place_details'))
                            ->helperText('Np. dokładny adres, brama, punkt orientacyjny.'),

                        Forms\Components\Select::make('markup_id')
                            ->label('Narzut')
                            ->options(Markup::pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->helperText('Kopiowany z szablonu, można zmienić'),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Uwagi operacyjne')
                    ->columns(1)
                    ->schema([
                        Forms\Components\RichEditor::make('office_notes')
                            ->label('Uwagi dla biura')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike', 'link', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'h2', 'h3', 'color', 'highlight', 'undo', 'redo'
                            ])
                            ->visible(fn (): bool => Schema::hasColumn('events', 'office_notes')),

                        Forms\Components\RichEditor::make('pilot_notes')
                            ->label('Uwagi dla pilota')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike', 'link', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'h2', 'h3', 'color', 'highlight', 'undo', 'redo'
                            ])
                            ->visible(fn (): bool => Schema::hasColumn('events', 'pilot_notes')),

                        Forms\Components\RichEditor::make('driver_notes')
                            ->label('Uwagi dla kierowcy')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike', 'link', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'h2', 'h3', 'color', 'highlight', 'undo', 'redo'
                            ])
                            ->visible(fn (): bool => Schema::hasColumn('events', 'driver_notes')),

                        Forms\Components\RichEditor::make('notes')
                            ->label('Uwagi ogólne')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike', 'link', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'h2', 'h3', 'color', 'highlight', 'undo', 'redo'
                            ])
                            ->placeholder('Dodatkowe uwagi widoczne globalnie dla imprezy.')
                            ->helperText('Tu możesz wpisać uwagi operacyjne do całej imprezy.'),
                    ])
                    ->collapsible(),
            ]);
    }

    protected static function refreshTotalCostFromTemplateState(callable $set, callable $get): void
    {
        $templateId = (int) ($get('event_template_id') ?? 0);
        $startPlaceId = (int) ($get('start_place_id') ?? 0);
        $participantCount = max(1, (int) ($get('participant_count') ?? 1));
        $gratisCount = max(0, (int) ($get('gratis_count') ?? 0));

        if (!$templateId || !$startPlaceId || $participantCount < 1) {
            $set('total_cost', 0);
            return;
        }

        $set('total_cost', static::resolveTotalCostFromTemplate($templateId, $startPlaceId, $participantCount, $gratisCount));
    }

    protected static function resolveTotalCostFromTemplate(int $templateId, int $startPlaceId, int $participantCount, int $gratisCount): float
    {
        $template = EventTemplate::find($templateId);

        if (!$template) {
            return 0.0;
        }

        try {
            $engine = new \App\Services\EventTemplateCalculationEngine();
            $exact = $engine->calculateDetailedForCustomGroup(
                $template,
                $participantCount,
                $gratisCount,
                $startPlaceId,
                null,
                false
            );

            if (!empty($exact)) {
                if (array_key_exists('price_base', $exact) && $exact['price_base'] !== null) {
                    return round((float) $exact['price_base'], 2);
                }

                if (array_key_exists('price_per_person', $exact) && $exact['price_per_person'] !== null) {
                    return round(((float) $exact['price_per_person']) * $participantCount, 2);
                }
            }
        } catch (\Throwable $e) {
            // fallback below
        }

        $priceRows = $template->pricesPerPerson()
            ->with('eventTemplateQty')
            ->where(function ($query) use ($startPlaceId) {
                $query->where(function ($q) use ($startPlaceId) {
                    $q->where('start_place_id', $startPlaceId)
                        ->orWhereNull('start_place_id');
                });
            })
            ->get();

        if ($priceRows->isEmpty()) {
            return 0.0;
        }

        $bestMatch = $priceRows
            ->sortBy(fn ($row) =>
                (((int) ($row->start_place_id ?? 0) === $startPlaceId) ? 0 : 1000000) +
                abs(((int) optional($row->eventTemplateQty)->qty) - $participantCount) +
                abs(((int) (optional($row->eventTemplateQty)->gratis ?? 0)) - $gratisCount)
            )
            ->first();

        if (!$bestMatch) {
            return 0.0;
        }

        if ($bestMatch->price_base !== null) {
            return round((float) $bestMatch->price_base, 2);
        }

        if ($bestMatch->price_with_tax !== null && $bestMatch->markup_amount !== null) {
            return round(max(0, ((float) $bestMatch->price_with_tax) - ((float) $bestMatch->markup_amount)), 2);
        }

        $variantQty = (int) optional($bestMatch->eventTemplateQty)->qty;
        if ($variantQty > 0) {
            return round(((float) $bestMatch->price_per_person) * $variantQty, 2);
        }

        return round(((float) $bestMatch->price_per_person) * $participantCount, 2);
    }

    public static function resolveTransferKmFromTemplateState(int $templateId, int $startPlaceId, float $fallback = 0.0): float
    {
        if ($templateId <= 0 || $startPlaceId <= 0) {
            return max(0, $fallback);
        }

        $template = EventTemplate::query()->find($templateId);
        if (!$template) {
            return max(0, $fallback);
        }

        $templateStartId = (int) ($template->start_place_id ?? 0);
        $templateEndId = (int) ($template->end_place_id ?? 0);

        $d1 = 0.0;
        $d2 = 0.0;

        if ($templateStartId > 0) {
            $d1 = (float) (PlaceDistance::query()
                ->where('from_place_id', $startPlaceId)
                ->where('to_place_id', $templateStartId)
                ->value('distance_km') ?? 0);
        }

        if ($templateEndId > 0) {
            $d2 = (float) (PlaceDistance::query()
                ->where('from_place_id', $templateEndId)
                ->where('to_place_id', $startPlaceId)
                ->value('distance_km') ?? 0);
        }

        $calculated = $d1 + $d2;

        if ($calculated <= 0) {
            $templateTransfer = (float) ($template->transfer_km ?? 0);
            return max(0, $templateTransfer > 0 ? $templateTransfer : $fallback);
        }

        return round($calculated, 2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('20s')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kod imprezy')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Skopiowano kod imprezy!'),
                // --- Termin + Nazwa + Szablon ---
                Tables\Columns\TextColumn::make('name')
                    ->label('Termin / Impreza')
                    ->searchable()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('start_date', $direction)->orderBy('name', $direction);
                    })
                    ->html()
                    ->state(function ($record): string {
                        $start = $record->start_date ? $record->start_date->format('d.m.Y') : '—';
                        $end   = $record->end_date   ? $record->end_date->format('d.m.Y')   : null;
                        $days  = max(1, (int) ($record->duration_days ?? 1));
                        $daysLabel = $days === 1 ? '1 dzień' : $days . ' dni';

                        $termin = '<div style="font-size:0.75rem;color:#6b7280;margin-bottom:2px">'
                            . e($start);
                        if ($end && $end !== $start) {
                            $termin .= ' – ' . e($end);
                        }
                        $termin .= ' <span style="color:#9ca3af">(' . e($daysLabel) . ')</span></div>';

                        $nazwa = '<div style="font-weight:700;font-size:0.9rem;color:#111827;line-height:1.3">'
                            . e($record->name ?? '—') . '</div>';

                        $szablon = '';
                        if ($record->eventTemplate?->name) {
                            $szablon = '<div style="font-size:0.72rem;color:#9ca3af;margin-top:2px">'
                                . e($record->eventTemplate->name) . '</div>';
                        }

                        return $termin . $nazwa . $szablon;
                    }),

                // --- Start / Klient ---
                Tables\Columns\TextColumn::make('client_name')
                    ->label('Start / Klient')
                    ->searchable()
                    ->html()
                    ->state(function ($record): string {
                        $parts = [];
                        if ($record->startPlace?->name) {
                            $parts[] = '<div style="font-size:0.75rem;color:#6b7280;margin-bottom:2px">'
                                . e($record->startPlace->name) . '</div>';
                        }
                        $parts[] = '<div style="font-weight:500">' . e($record->client_name ?? '—') . '</div>';
                        if ($record->client_phone) {
                            $parts[] = '<div style="font-size:0.75rem;color:#6b7280">' . e($record->client_phone) . '</div>';
                        }
                        if ($record->client_email) {
                            $parts[] = '<div style="font-size:0.75rem;color:#6b7280">' . e($record->client_email) . '</div>';
                        }
                        return implode('', $parts);
                    }),

                // --- Uczestnicy: X+Y(gratis) ---
                Tables\Columns\TextColumn::make('participant_count')
                    ->label('Uczestnicy')
                    ->sortable()
                    ->alignCenter()
                    ->html()
                    ->state(function ($record): string {
                        $total  = (int) ($record->participant_count ?? 0);
                        $gratis = 0;
                        try {
                            $gratis = $record->resolveGratisCountForParticipantCount($total);
                        } catch (\Throwable) {}

                        $base = '<span style="font-weight:700;font-size:0.9rem">' . e($total) . '</span>';
                        $gr   = $gratis > 0
                            ? '<span style="color:#6b7280;font-size:0.8rem">+' . e($gratis) . '</span>'
                            : '';
                        return $base . $gr;
                    }),

                // --- Finanse: Do zapłaty / Zapłacono (X/Y) / Brakuje ---
                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Finanse')
                    ->sortable()
                    ->html()
                    ->state(function ($record): string {
                        $fmt = fn ($v) => number_format((float) ($v ?? 0), 2, ',', ' ') . ' PLN';

                        $participantCount = max(1, (int) ($record->participant_count ?? 1));

                        // Priorytet: dokładnie ta sama kalkulacja co widżet "Kalkulacje cen"
                        // (SUMA KOŃCOWA dla PLN i Cena za osobę).
                        $dueAmount = null;
                        $pricePerPerson = null;

                        try {
                            static $calcCache = [];
                            $cacheKey = (int) ($record->id ?? 0);

                            if (!array_key_exists($cacheKey, $calcCache)) {
                                $widget = app(\App\Filament\Resources\EventResource\Widgets\EventPriceTable::class);
                                $widget->record = $record;
                                $widget->loadCalculations();

                                $pln = $widget->detailedCalculations[$participantCount]['PLN'] ?? null;
                                $calcCache[$cacheKey] = [
                                    'total' => is_array($pln) ? (float) ($pln['total'] ?? 0) : 0.0,
                                ];
                            }

                            $dueAmount = (float) ($calcCache[$cacheKey]['total'] ?? 0);
                            if ($dueAmount > 0) {
                                $pricePerPerson = round($dueAmount / $participantCount, 2);
                            }
                        } catch (\Throwable) {
                            // Fallback gdy szczegółowa kalkulacja nie jest dostępna.
                        }

                        if ($dueAmount === null || $dueAmount <= 0) {
                            $dueAmount = (float) ($record->total_cost ?? 0);
                        }

                        if ($pricePerPerson === null || $pricePerPerson <= 0) {
                            $pricePerPerson = round($dueAmount / $participantCount, 2);
                        }

                        // Zapłacono: suma wpłat z umów indywidualnych.
                        $paidAmount = (float) ($record->agreements_amount_paid_total ?? 0);

                        $totalCount = (int) ($record->participant_count ?? 0);
                        $paidCount  = (int) ($record->paid_participants_count ?? 0);
                        $brakuje    = max(0.0, $dueAmount - $paidAmount);

                        $row = fn (string $label, string $value, string $vColor = '#111827') =>
                            '<tr>'
                            . '<td style="padding:1px 8px 1px 0;color:#9ca3af;font-size:0.72rem;white-space:nowrap">' . $label . '</td>'
                            . '<td style="color:' . $vColor . ';font-size:0.78rem;font-weight:600;white-space:nowrap">' . $value . '</td>'
                            . '</tr>';

                        $paidDisplay = e($fmt($paidAmount));
                        if ($totalCount > 0) {
                            $paidDisplay .= ' <span style="color:#9ca3af;font-weight:400;font-size:0.7rem">('
                                . $paidCount . '/' . $totalCount . ')</span>';
                        }

                        $brakujeColor = $brakuje > 0.001 ? '#dc2626' : '#047857';

                        return '<table style="border-collapse:collapse">'
                            . $row('Do zapłaty (łącznie):', e($fmt($dueAmount)))
                            . $row('Cena za os.:', number_format($pricePerPerson, 2, ',', ' ') . ' PLN', '#1f2937')
                            . '<tr>'
                            . '<td style="padding:1px 8px 1px 0;color:#9ca3af;font-size:0.72rem;white-space:nowrap">Zapłacono:</td>'
                            . '<td style="color:#047857;font-size:0.78rem;font-weight:600;white-space:nowrap">' . $paidDisplay . '</td>'
                            . '</tr>'
                            . $row('Brakuje:', e($fmt($brakuje)), $brakujeColor)
                            . '</table>';
                    }),

                // --- Uwagi biura ---
                Tables\Columns\TextColumn::make('office_notes')
                    ->label('Uwagi biura')
                    ->limit(40)
                    ->tooltip(fn ($record): ?string => ($record->office_notes ?? null) ?: null)
                    ->placeholder('—')
                    ->visible(fn (): bool => Schema::hasColumn('events', 'office_notes'))
                    ->toggleable(),

                // --- Status z możliwością zmiany inline ---
                Tables\Columns\SelectColumn::make('status')
                    ->label('Status')
                    ->options(Event::getStatusOptions())
                    ->sortable()
                    ->selectablePlaceholder(false),

                // --- Ukryte domyślnie ---
                Tables\Columns\TextColumn::make('departure_time')
                    ->label('Godzina podstawienia')
                    ->state(fn ($record) => $record->departure_time ?: '—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => Schema::hasColumn('events', 'departure_time')),

                Tables\Columns\TextColumn::make('transport_company_name')
                    ->label('Firma transportowa')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => Schema::hasColumn('events', 'transport_company_name')),

                Tables\Columns\TextColumn::make('driver_name')
                    ->label('Kierowca')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => Schema::hasColumn('events', 'driver_name')),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzona')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Event::getStatusOptions()),

                Tables\Filters\SelectFilter::make('event_template_id')
                    ->label('Szablon')
                        ->options(EventTemplate::where('deleted_at', null)->pluck('name', 'id'))
                        ->searchable(),

                Tables\Filters\SelectFilter::make('start_place_id')
                    ->label('Miejsce podstawienia')
                    ->options(Place::query()->pluck('name', 'id'))
                    ->searchable(),

                Tables\Filters\Filter::make('transport_company_name')
                    ->label('Firma transportowa')
                    ->visible(fn (): bool => Schema::hasColumn('events', 'transport_company_name'))
                    ->form([
                        Forms\Components\TextInput::make('value')
                            ->label('Nazwa firmy'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, $value): Builder => $query->where('transport_company_name', 'like', '%' . $value . '%'),
                        );
                    }),

                Tables\Filters\Filter::make('driver_name')
                    ->label('Kierowca')
                    ->visible(fn (): bool => Schema::hasColumn('events', 'driver_name'))
                    ->form([
                        Forms\Components\TextInput::make('value')
                            ->label('Imię i nazwisko kierowcy'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, $value): Builder => $query->where('driver_name', 'like', '%' . $value . '%'),
                        );
                    }),
                
                Tables\Filters\Filter::make('start_date')
                    ->label('Data rozpoczęcia')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Od'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Do'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('start_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('start_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Event $record) => $record->status === Event::STATUS_INQUIRY),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_status')
                        ->label('Zmień status')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->label('Nowy status')
                                ->options(Event::getStatusOptions())
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Support\Collection $records, array $data): void {
                            $records->each(fn (Event $record) => $record->changeStatus($data['status']));
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        $relations = [
            RelationManagers\ProgramPointsRelationManager::class,
            RelationManagers\PricePerPersonRelationManager::class,
            RelationManagers\HistoryRelationManager::class,
            RelationManagers\SnapshotsRelationManager::class,
            SharedTasksRelationManager::class,
            RelationManagers\SettlementsRelationManager::class,
            RelationManagers\ReservationsRelationManager::class,
        ];

        if (Schema::hasTable('event_agreements')) {
            $relations[] = RelationManagers\AgreementsRelationManager::class;
        }

        if (Schema::hasTable('event_documents')) {
            $relations[] = RelationManagers\DocumentsRelationManager::class;
        }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
            'edit-program' => Pages\EditEventProgram::route('/{record}/program'),
            'calculation' => Pages\EventCalculation::route('/{record}/calculation'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'in_progress')->count();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (!Schema::hasTable('event_agreements')) {
            return $query->with(['qtyVariants:id,event_id,qty,gratis']);
        }

        $query->withCount([
            'agreements',
            'agreements as agreements_paid_count' => fn (Builder $query) => $query->where('payment_status', 'paid'),
        ])->withSum([
            'agreements as paid_participants_count' => fn (Builder $query) => $query->where('payment_status', 'paid'),
        ], 'participant_count')->withSum('agreements as agreements_amount_paid_total', 'amount_paid')
            ->with(['qtyVariants:id,event_id,qty,gratis']);

        return $query;
    }
}
