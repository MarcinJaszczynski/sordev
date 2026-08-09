<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EventKeyInfoFields;
use App\Filament\Forms\EventNotesFields;
use App\Filament\Forms\EventReadinessFields;
use App\Filament\Forms\EventTransportFields;
use App\Filament\Forms\TypedContractorSelect;
use App\Filament\Resources\EventResource\Pages;
use App\Filament\Resources\EventResource\Traits\SearchContractorTrait;
use App\Models\Bus;
use App\Models\Contractor;
use App\Models\ContractorType;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Place;
use App\Models\PlaceDistance;
use App\Models\TransportType;
use App\Support\EventListFinanceColumn;
use App\Support\EventReadinessIndicators;
use App\Support\ExecutiveAccess;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class EventResource extends Resource
{
    use SearchContractorTrait;

    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_OPERATIONS;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $navigationLabel = 'Imprezy';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                ...EventKeyInfoFields::identitySection(),
                ...EventKeyInfoFields::basicSection(),
                ...EventReadinessFields::officeSection(),
            ]);
    }

    protected static function financialSummarySection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Finanse')
            ->icon('heroicon-o-banknotes')
            ->description('Cena z kalkulacji, rozliczenie biura i wpłaty klientów.')
            ->columns(2)
            ->hidden(fn (string $operation) => $operation !== 'edit')
            ->schema([
                Forms\Components\Placeholder::make('fs_calc_cost')
                    ->label('Cena z kalkulacji')
                    ->helperText('Na podstawie szablonu, km i liczby uczestników')
                    ->content(function ($record, callable $get): string {
                        if (! $record) {
                            return '—';
                        }

                        $participantCount = max(1, (int) ($get('participant_count') ?? $record->participant_count ?? 1));

                        try {
                            $widget = app(\App\Filament\Resources\EventResource\Widgets\EventPriceTable::class);
                            $widget->record = $record;
                            $widget->loadCalculations();

                            $plnData = $widget->detailedCalculations[$participantCount]['PLN'] ?? null;
                            $totalCost = $plnData ? round((float) ($plnData['total'] ?? 0), 2) : 0.0;
                            $perPerson = $participantCount > 0 ? $totalCost / $participantCount : 0;

                            return 'Suma: '.number_format($totalCost, 2, ',', ' ')." PLN\n"
                                .'Za osobę: '.number_format($perPerson, 2, ',', ' ').' PLN';
                        } catch (\Throwable $e) {
                            return 'Brak danych kalkulacji';
                        }
                    })
                    ->extraAttributes(['class' => 'whitespace-pre-line']),

                Forms\Components\Placeholder::make('fs_snapshot_diff')
                    ->label('Snapshot vs kalkulacja')
                    ->helperText('Porównanie z pierwotnym snapshotem przy tworzeniu imprezy')
                    ->content(function ($record, callable $get): string {
                        if (! $record) {
                            return '—';
                        }

                        $snapshot = $record->originalSnapshot;
                        $baseline = (float) ($snapshot?->total_cost_snapshot ?? 0);
                        if ($baseline <= 0) {
                            return 'Brak snapshotu pierwotnego.';
                        }

                        $participantCount = max(1, (int) ($get('participant_count') ?? $record->participant_count ?? 1));

                        try {
                            $widget = app(\App\Filament\Resources\EventResource\Widgets\EventPriceTable::class);
                            $widget->record = $record;
                            $widget->loadCalculations();
                            $plnData = $widget->detailedCalculations[$participantCount]['PLN'] ?? null;
                            $current = $plnData ? round((float) ($plnData['total'] ?? 0), 2) : 0.0;
                        } catch (\Throwable) {
                            return 'Nie udało się obliczyć kalkulacji bieżącej.';
                        }

                        $diff = round($current - $baseline, 2);
                        $pct = $baseline > 0 ? round(($diff / $baseline) * 100, 1) : 0.0;
                        $sign = $diff >= 0 ? '+' : '';

                        return 'Snapshot: '.number_format($baseline, 2, ',', ' ')." PLN\n"
                            .'Bieżąca: '.number_format($current, 2, ',', ' ')." PLN\n"
                            .'Różnica: '.$sign.number_format($diff, 2, ',', ' ').' PLN ('.$sign.$pct.'%)';
                    })
                    ->extraAttributes(['class' => 'whitespace-pre-line']),

                Forms\Components\Placeholder::make('fs_planned_cost')
                    ->label('Do zapłaty przez biuro')
                    ->helperText('Z aktywnego rozliczenia')
                    ->content(function ($record): string {
                        if (! $record) {
                            return '—';
                        }
                        try {
                            $s = $record->settlements()
                                ->whereIn('status', ['draft', 'active', 'pilot_settled'])
                                ->latest('id')->first();
                            if ($s && $s->planned_cost_pln !== null) {
                                return number_format((float) $s->planned_cost_pln, 2, ',', ' ').' PLN';
                            }
                        } catch (\Throwable) {
                        }

                        return '— (brak rozliczenia)';
                    }),

                Forms\Components\Placeholder::make('fs_actual_cost')
                    ->label('Zapłacono przez biuro')
                    ->helperText('Suma kwot przelanych do wykonawców')
                    ->content(function ($record): string {
                        if (! $record) {
                            return '—';
                        }
                        try {
                            $s = $record->settlements()
                                ->whereIn('status', ['draft', 'active', 'pilot_settled'])
                                ->latest('id')->first();
                            if ($s && $s->actual_cost_pln !== null) {
                                return number_format((float) $s->actual_cost_pln, 2, ',', ' ').' PLN';
                            }
                        } catch (\Throwable) {
                        }

                        return '— (brak rozliczenia)';
                    }),

                Forms\Components\Placeholder::make('fs_clients_paid')
                    ->label('Wpłaty klientów')
                    ->helperText('Suma wpłat ze wszystkich umów')
                    ->content(function ($record): string {
                        if (! $record) {
                            return '—';
                        }
                        try {
                            if ($record->agreements()->doesntExist()) {
                                return '— (brak umów)';
                            }
                            $paid = $record->agreements()->sum('amount_paid');

                            return number_format((float) $paid, 2, ',', ' ').' PLN';
                        } catch (\Throwable) {
                        }

                        return '—';
                    }),

                Forms\Components\TextInput::make('total_cost')
                    ->label('Cena z kalkulacji (PLN)')
                    ->numeric()
                    ->suffix('PLN')
                    ->default(0)
                    ->readOnly()
                    ->hiddenOn('edit')
                    ->helperText('Obliczana automatycznie na podstawie szablonu.'),
            ]);
    }

    public static function carrierAndDriverSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Przewoźnik i kierowca')
            ->icon('heroicon-o-truck')
            ->description('Firma transportowa, autokar, miejsce podstawienia i dane kierowcy.')
            ->columns(3)
            ->schema([
                ...TypedContractorSelect::make(
                    field: 'transport_contractor_id',
                    label: 'Firma transportowa',
                    typeNames: ContractorType::transportTypeNames(),
                    searchAllField: 'transport_contractor_search_all',
                    defaultTypeOnCreate: 'przewoźnik',
                    helperText: 'Wybierz firmę z listy, wyszukaj po nazwie lub dodaj nową.',
                    afterStateUpdated: function ($state, callable $set): void {
                        if (! Schema::hasColumn('events', 'transport_company_name')) {
                            return;
                        }

                        if (! $state) {
                            $set('transport_company_name', null);

                            return;
                        }

                        $name = Contractor::query()->whereKey($state)->value('name');
                        $set('transport_company_name', $name ?: null);
                    },
                    columnSpan: 'full',
                ),

                Forms\Components\Hidden::make('transport_company_name')
                    ->visible(fn (): bool => Schema::hasColumn('events', 'transport_company_name')),

                Forms\Components\Select::make('bus_id')
                    ->label('Autokar')
                    ->options(Bus::pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(fn ($livewire) => $livewire->dispatch('event-price-table-refresh')),

                Forms\Components\Select::make('start_place_id')
                    ->label('Miejsce wyjazdu (podstawienia)')
                    ->options(fn (callable $get, ?Event $record) => Place::startingPlaceSelectOptionsForTemplate(
                        (int) ($get('event_template_id') ?? $record?->event_template_id ?? 0) ?: null,
                        (int) ($get('start_place_id') ?? $record?->start_place_id ?? 0) ?: null,
                    ))
                    ->searchable()
                    ->nullable()
                    ->reactive()
                    ->helperText(fn (callable $get, ?Event $record): string => filled($get('event_template_id') ?? $record?->event_template_id)
                        ? 'Punkty startowe dostępne dla szablonu tej imprezy.'
                        : 'Tylko miejsca oznaczone jako punkty startowe — wymagane do kalkulacji transferu.')
                    ->afterStateUpdated(function (callable $get, callable $set, ?\App\Models\Event $record): void {
                        $templateId = (int) ($get('event_template_id') ?? $record?->event_template_id ?? 0);
                        $startPlaceId = (int) ($get('start_place_id') ?? 0);
                        $currentTransfer = (float) ($get('transfer_km') ?? 0);

                        if ($templateId) {
                            $set('transfer_km', static::resolveTransferKmFromTemplateState(
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

                        static::refreshTotalCostFromTemplateState($set, $get, $record);
                    }),

                Forms\Components\Select::make('program_start_place_id')
                    ->label('Początek programu')
                    ->options(\App\Models\Place::pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->dehydrated(false)
                    ->reactive()
                    ->visible(fn (callable $get, ?\App\Models\Event $record) => empty($get('event_template_id')) && ! ($record?->event_template_id))
                    ->afterStateUpdated(function (callable $get, callable $set, ?\App\Models\Event $record): void {
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
                    ->helperText('Służy tylko do przeliczenia transferu (x2).'),

                Forms\Components\TextInput::make('transfer_km')
                    ->label('Km transferu')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($livewire) => method_exists($livewire, 'dispatch') ? $livewire->dispatch('event-price-table-refresh') : null),

                Forms\Components\TextInput::make('program_km')
                    ->label('Km programu')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($livewire, callable $get, callable $set, ?Event $record) => [
                        static::refreshTotalCostFromTemplateState($set, $get, $record),
                        method_exists($livewire, 'dispatch') ? $livewire->dispatch('event-price-table-refresh') : null,
                    ]),

                ...EventTransportFields::manualTransportCostFields(),

                Forms\Components\Textarea::make('bus_info')
                    ->label('Informacje o autokarze')
                    ->rows(2)
                    ->maxLength(1000)
                    ->columnSpanFull()
                    ->visible(fn (): bool => Schema::hasColumn('events', 'bus_info')),

                Forms\Components\Fieldset::make('Kierowca i podstawienie')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema(EventReadinessFields::driverFields()),

                Forms\Components\Placeholder::make('transport_from_program')
                    ->label('Transport w programie')
                    ->hiddenOn('create')
                    ->content(function (?Event $record): \Illuminate\Support\HtmlString {
                        if (! $record) {
                            return new \Illuminate\Support\HtmlString('<span style="color:#9ca3af">Brak danych</span>');
                        }

                        $transportPoints = $record->transportProgramPoints()->get();
                        if ($transportPoints->isEmpty()) {
                            return new \Illuminate\Support\HtmlString('<span style="color:#9ca3af">Brak punktów oznaczonych jako transport.</span>');
                        }

                        $rows = $transportPoints->map(function ($point) {
                            $name = e($point->name ?? $point->templatePoint?->name ?? '—');
                            $contractor = e($point->contractor?->name ?? '—');
                            $day = (int) ($point->day ?? 1);

                            return '<tr>'
                                .'<td style="padding:4px 12px 4px 0;color:#6b7280;white-space:nowrap">Dzień '.$day.'</td>'
                                .'<td style="padding:4px 12px 4px 0;font-weight:500">🚌 '.$name.'</td>'
                                .'<td style="padding:4px 0;color:#374151">'.$contractor.'</td>'
                                .'</tr>';
                        })->implode('');

                        return new \Illuminate\Support\HtmlString(
                            '<table style="border-collapse:collapse;font-size:0.85rem">'.$rows.'</table>'
                        );
                    })
                    ->columnSpanFull(),

                EventNotesFields::driverNotes()
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('transport_notes_stack')
                    ->hiddenLabel()
                    ->content(fn (?Event $record) => view('filament.components.sticky-notes-stack', [
                        'notableType' => Event::class,
                        'notableId' => $record?->id,
                        'title' => 'Notatki - Transport',
                        'filterCategory' => \App\Support\StickyNotes\StickyNoteCategory::TRANSPORT,
                    ]))
                    ->hiddenOn('create')
                    ->columnSpanFull(),
            ]);
    }

    public static function hotelSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Hotel')
            ->icon('heroicon-o-building-office-2')
            ->description('Hotele z programu i uwagi dla hotelu.')
            ->hiddenOn('create')
            ->schema([
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('open_hotel_plan')
                        ->label('Edytuj plan noclegów')
                        ->icon('heroicon-o-building-office-2')
                        ->url(fn (?Event $record) => $record
                            ? EventResource::getUrl('hotel-planning', ['record' => $record->id])
                            : null)
                        ->visible(fn (?Event $record): bool => (bool) $record),
                ]),

                Forms\Components\Placeholder::make('hotels_from_program')
                    ->label('Hotele z programu')
                    ->content(function (?Event $record): \Illuminate\Support\HtmlString {
                        if (! $record) {
                            return new \Illuminate\Support\HtmlString('<span style="color:#9ca3af">Brak danych</span>');
                        }
                        $hotels = $record->hotelProgramPoints()->get();
                        if ($hotels->isEmpty()) {
                            return new \Illuminate\Support\HtmlString('<span style="color:#9ca3af">Brak punktów programu oznaczonych jako nocleg.</span>');
                        }
                        $rows = $hotels->map(function ($point) {
                            $name = e($point->name ?? $point->templatePoint?->name ?? '—');
                            $contractor = e($point->contractor?->name ?? '—');
                            $day = (int) ($point->day ?? 1);

                            return '<tr>'
                                .'<td style="padding:4px 12px 4px 0;color:#6b7280;white-space:nowrap">Dzień '.$day.'</td>'
                                .'<td style="padding:4px 12px 4px 0;font-weight:500">🏨 '.$name.'</td>'
                                .'<td style="padding:4px 0;color:#374151">'.$contractor.'</td>'
                                .'</tr>';
                        })->implode('');

                        return new \Illuminate\Support\HtmlString(
                            '<table style="border-collapse:collapse;font-size:0.85rem">'.$rows.'</table>'
                        );
                    }),

                Forms\Components\Placeholder::make('hotel_notes_stack')
                    ->hiddenLabel()
                    ->content(fn (?Event $record) => view('filament.components.sticky-notes-stack', [
                        'notableType' => Event::class,
                        'notableId' => $record?->id,
                        'title' => 'Notatki - Hotel',
                        'filterCategory' => \App\Support\StickyNotes\StickyNoteCategory::HOTEL,
                    ]))
                    ->hiddenOn('create')
                    ->columnSpanFull(),
            ]);
    }

    public static function syncGratisCountFromQtyVariant(callable $set, callable $get, Event $record): void
    {
        $participantCount = max(1, (int) ($get('participant_count') ?? 1));

        $exactVariant = $record->qtyVariants()
            ->where('qty', $participantCount)
            ->orderBy('id')
            ->first();

        if ($exactVariant) {
            $set('gratis_count', max(0, (int) ($exactVariant->gratis ?? 0)));
        }
    }

    public static function refreshTotalCostFromTemplateState(callable $set, callable $get, ?Event $record = null): void
    {
        $templateId = (int) ($get('event_template_id') ?? $record?->event_template_id ?? 0);
        $startPlaceId = (int) ($get('start_place_id') ?? $record?->start_place_id ?? 0);
        $participantCount = max(1, (int) ($get('participant_count') ?? $record?->participant_count ?? 1));
        $gratisCount = max(0, (int) ($get('gratis_count') ?? 0));

        if (! $templateId || ! $startPlaceId || $participantCount < 1) {
            $set('total_cost', 0);

            return;
        }

        $set('total_cost', static::resolveTotalCostFromTemplate($templateId, $startPlaceId, $participantCount, $gratisCount));
    }

    protected static function resolveTotalCostFromTemplate(int $templateId, int $startPlaceId, int $participantCount, int $gratisCount): float
    {
        $template = EventTemplate::find($templateId);

        if (! $template) {
            return 0.0;
        }

        try {
            $engine = new \App\Services\EventTemplateCalculationEngine;
            $exact = $engine->calculateDetailedForCustomGroup(
                $template,
                $participantCount,
                $gratisCount,
                $startPlaceId,
                null,
                false
            );

            if (! empty($exact)) {
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
            ->sortBy(fn ($row) => (((int) ($row->start_place_id ?? 0) === $startPlaceId) ? 0 : 1000000) +
                abs(((int) optional($row->eventTemplateQty)->qty) - $participantCount) +
                abs(((int) (optional($row->eventTemplateQty)->gratis ?? 0)) - $gratisCount)
            )
            ->first();

        if (! $bestMatch) {
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
        if (! $template) {
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
            ->columns([
                // Kod imprezy zostanie wyświetlony razem z terminem i nazwą
                // --- Termin + Nazwa + Szablon ---
                Tables\Columns\TextColumn::make('name')
                    ->label('Termin / Impreza')
                    ->searchable(['name', 'code'])
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('start_date', $direction)->orderBy('name', $direction);
                    })
                    ->html()
                    ->state(function ($record): string {
                        $start = $record->start_date ? $record->start_date->format('d.m.Y') : '—';
                        $end = $record->end_date ? $record->end_date->format('d.m.Y') : null;
                        $days = max(1, (int) ($record->duration_days ?? 1));
                        $daysLabel = $days === 1 ? '1 dzień' : $days.' dni';

                        $termin = '<div class="admin-event-termin">'
                            .e($start);
                        if ($end && $end !== $start) {
                            $termin .= ' – '.e($end);
                        }
                        $termin .= ' <span style="color:#6b7280;font-size:0.85rem">('.e($daysLabel).')</span></div>';

                        $nazwa = '<div style="font-size:0.95rem;font-weight:600;line-height:1">'.e($record->name ?? '—').'</div>';

                        $szablon = '';
                        if ($record->eventTemplate?->name) {
                            $szablon = '<div class="admin-event-template">'
                                .e($record->eventTemplate->name).'</div>';
                        }

                        $codeHtml = '';
                        if ($record->code) {
                            $codeHtml = '<div style="color:#6b7280;font-size:0.85rem">['.e($record->code).']</div>';
                        }

                        return $termin.$nazwa.$codeHtml.$szablon;
                    }),

                Tables\Columns\SelectColumn::make('status')
                    ->label('Status')
                    ->options(Event::getStatusOptions())
                    ->sortable()
                    ->selectablePlaceholder(false)
                    ->updateStateUsing(function (Event $record, ?string $state): string {
                        if ($state === null || $state === $record->status) {
                            return (string) $record->status;
                        }

                        $record->changeStatus($state);

                        return (string) $record->fresh()->status;
                    })
                    ->extraCellAttributes(fn (Event $record): array => [
                        'class' => Event::statusListCellClass($record->status),
                    ]),

                // --- Start / Klient ---
                Tables\Columns\TextColumn::make('client_name')
                    ->label('Start / Klient')
                    ->visibleFrom('md')
                    ->searchable()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->leftJoin('places', 'events.start_place_id', '=', 'places.id')
                            ->orderBy('places.name', $direction)
                            ->orderBy('events.client_name', $direction)
                            ->select('events.*');
                    })
                    ->html()
                    ->state(function ($record): string {
                        $parts = [];
                        if ($record->startPlace?->name) {
                            $parts[] = '<div class="admin-event-client-start">'
                                .e($record->startPlace->name).'</div>';
                        }
                        $parts[] = '<div class="admin-event-client-name">'.e($record->formattedOrderingPartiesNames()).'</div>';
                        if ($record->client_phone) {
                            $parts[] = '<div class="admin-event-client-meta">'.e($record->client_phone).'</div>';
                        }
                        if ($record->client_email) {
                            $parts[] = '<div class="admin-event-client-meta">'.e($record->client_email).'</div>';
                        }

                        return implode('', $parts);
                    }),

                // --- Uczestnicy: X+Y(gratis) ---
                Tables\Columns\TextColumn::make('participant_count')
                    ->label('Uczestnicy')
                    ->visibleFrom('md')
                    ->sortable()
                    ->alignCenter()
                    ->html()
                    ->state(function ($record): string {
                        $total = (int) ($record->participant_count ?? 0);
                        $gratis = 0;
                        try {
                            $gratis = $record->resolveGratisCountForParticipantCount($total);
                        } catch (\Throwable) {
                        }

                        $base = '<span style="font-weight:700;font-size:0.9rem">'.e($total).'</span>';
                        $gr = $gratis > 0
                            ? '<span style="color:#6b7280;font-size:0.8rem">+'.e($gratis).'</span>'
                            : '';

                        return $base.$gr;
                    }),

                Tables\Columns\TextColumn::make('margin_plan_vs_settlement')
                    ->label('Marża plan')
                    ->visibleFrom('xl')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => ExecutiveAccess::canViewFinalFinancialResults())
                    ->html()
                    ->state(function (Event $record): string {
                        try {
                            $calcPlan = (float) ($record->total_cost ?? 0);
                            $settlementPlan = (float) ($record->activeSettlement?->planned_cost_pln ?? 0);
                            $delta = $settlementPlan - $calcPlan;
                            $percent = $calcPlan > 0
                                ? round(($delta / $calcPlan) * 100, 1)
                                : null;
                            $warn = $settlementPlan > 0
                                && $percent !== null
                                && abs($percent) >= 5.0;
                            $fmt = fn ($v) => number_format($v, 2, ',', ' ').' PLN';
                            $color = $warn ? '#dc2626' : '#047857';

                            $deltaLine = '<div style="color:'.$color.';font-weight:600">Δ '.e($fmt($delta));
                            if ($percent !== null) {
                                $deltaLine .= ' ('.e(number_format($percent, 1, ',', ' ')).'%)';
                            }
                            $deltaLine .= '</div>';

                            $html = '<div style="font-size:0.78rem;line-height:1.4">'
                                .'<div><span style="color:#6b7280">Kalkulacja:</span> '.e($fmt($calcPlan)).'</div>'
                                .'<div><span style="color:#6b7280">Rozliczenie:</span> '.e($fmt($settlementPlan)).'</div>'
                                .$deltaLine;
                            if ($warn) {
                                $html .= '<div style="color:#dc2626;font-size:0.7rem">Rozbieżność &gt; 5%</div>';
                            }
                            $html .= '</div>';

                            return $html;
                        } catch (\Throwable) {
                            return '<span style="color:#9ca3af">—</span>';
                        }
                    }),

                // --- Finanse: Do zapłaty / Zapłacono (X/Y) / Brakuje ---
                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Finanse')
                    ->visibleFrom('md')
                    ->sortable()
                    ->html()
                    ->state(function (Event $record, $livewire): string {
                        $currencyCode = EventListFinanceColumn::resolveCurrencyCode($livewire);

                        return EventListFinanceColumn::html($record, $currencyCode);
                    }),

                // --- Uwagi biura ---
                Tables\Columns\TextColumn::make('office_notes')
                    ->label('Notatki biura')
                    ->visibleFrom('xl')
                    ->label('Uwagi biura')
                    ->limit(40)
                    ->tooltip(fn ($record): ?string => ($record->office_notes ?? null) ?: null)
                    ->placeholder('—')
                    ->visible(fn (): bool => Schema::hasColumn('events', 'office_notes'))
                    ->toggleable(),

                // Kolumna: Pilot / Transport / Hotele (osobno, przed gotowością)
                Tables\Columns\TextColumn::make('pilot_transport_hotels')
                    ->label('Pilot / Transport / Hotele')
                    ->visibleFrom('lg')
                    ->html()
                    ->state(function (Event $record): string {
                        $pilot = e($record->assignedUser?->name ?? '—');
                        $transportCompany = e($record->transportContractor?->name ?? $record->transport_company_name ?? '—');
                        $driverParts = array_filter([
                            $record->driver_name ?: null,
                            $record->driver_phone ?: null,
                            $record->vehicle_registration ? 'rej. '.$record->vehicle_registration : null,
                        ]);
                        $transportExtra = ! empty($driverParts) ? e(implode(', ', $driverParts)) : '—';

                        $hotels = $record->hotelProgramPoints;
                        $hotelsLine = $hotels->isEmpty()
                            ? '—'
                            : $hotels->map(function ($point) {
                                $name = e($point->contractor?->name ?? $point->name ?? '—');
                                $day = (int) ($point->day ?? 1);

                                return 'Dz.'.$day.' '.$name;
                            })->implode('<br>');

                        $row = fn (string $label, string $value): string => '<tr>'
                            .'<td style="padding:1px 8px 1px 0;color:#9ca3af;font-size:0.72rem;white-space:nowrap;vertical-align:top">'.$label.'</td>'
                            .'<td style="color:#111827;font-size:0.78rem;font-weight:600;line-height:1.25">'.$value.'</td>'
                            .'</tr>';

                        $transportValue = $transportCompany;
                        if ($transportExtra && $transportExtra !== '—') {
                            $transportValue .= '<br><span style="color:#9ca3af;font-weight:400;font-size:0.72rem">'.$transportExtra.'</span>';
                        }

                        $hotelsValue = $hotelsLine !== '—'
                            ? $hotelsLine
                            : '—';

                        return '<table style="border-collapse:collapse">'
                            .$row('Pilot:', $pilot)
                            .$row('Transport:', $transportValue)
                            .$row('Hotele:', $hotelsValue)
                            .'</table>';
                    })
                    ->wrap(),

                // Gotowość — tylko wskaźnik gotowości, bez danych o pilocie/transporcie/hotelach
                Tables\Columns\TextColumn::make('operational_indicators')
                    ->label('Gotowość')
                    ->alignCenter()
                    ->html()
                    ->state(fn (Event $record): string => EventReadinessIndicators::renderHtml($record))
                    ->tooltip('Przejdź do edycji imprezy, aby zmienić gotowość')
                    ->extraCellAttributes(['class' => 'event-readiness-cell']),

                // --- Ukryte domyślnie ---
                Tables\Columns\TextColumn::make('substitution_time')
                    ->label('Godz. podstawienia')
                    ->state(fn ($record) => $record->substitution_time
                        ? substr((string) $record->substitution_time, 0, 5)
                        : '—')
                    ->visible(fn (): bool => Schema::hasColumn('events', 'substitution_time')),
                Tables\Columns\TextColumn::make('departure_time')
                    ->label('Godz. wyjazdu')
                    ->state(fn ($record) => $record->departure_time
                        ? substr((string) $record->departure_time, 0, 5)
                        : '—')
                    ->visible(fn (): bool => Schema::hasColumn('events', 'departure_time')),
                Tables\Columns\TextColumn::make('return_time')
                    ->label('Godz. powrotu')
                    ->state(fn ($record) => $record->return_time
                        ? substr((string) $record->return_time, 0, 5)
                        : '—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => Schema::hasColumn('events', 'return_time')),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->label('Utworzona')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('finance_display_currency')
                    ->label('Waluta (Finanse)')
                    ->form([
                        Forms\Components\Select::make('code')
                            ->label('Waluta w kolumnie Finanse')
                            ->options(fn (): array => Currency::query()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(function (Currency $currency): array {
                                    $code = strtoupper((string) ($currency->symbol ?: $currency->name));

                                    return [$code => trim($currency->name.' ('.$currency->symbol.')')];
                                })
                                ->all())
                            ->default('PLN')
                            ->selectablePlaceholder(false)
                            ->live(),
                    ])
                    ->query(fn (Builder $query): Builder => $query)
                    ->indicateUsing(function (array $data): array {
                        $code = strtoupper((string) ($data['code'] ?? 'PLN'));

                        if ($code === 'PLN') {
                            return [];
                        }

                        return [
                            Tables\Filters\Indicator::make('Waluta: '.$code)
                                ->removeField('code'),
                        ];
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Event::getStatusOptions()),

                Tables\Filters\SelectFilter::make('event_template_id')
                    ->label('Szablon')
                    ->options(EventTemplate::where('deleted_at', null)->pluck('name', 'id'))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('start_place_id')
                    ->label('Miejsce podstawienia')
                    ->options(fn () => Place::startingPlaceSelectOptions())
                    ->searchable(),

                Tables\Filters\SelectFilter::make('transport_type')
                    ->label('Rodzaj transportu')
                    ->multiple()
                    ->options(fn (): array => TransportType::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $values = $data['values'] ?? [];

                        if (empty($values)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'eventTemplate',
                            fn (Builder $templateQuery): Builder => $templateQuery->withExactTransportTypes($values)
                        );
                    }),

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
                            fn (Builder $query, $value): Builder => $query
                                ->where(function (Builder $innerQuery) use ($value): void {
                                    $innerQuery
                                        ->where('transport_company_name', 'like', '%'.$value.'%')
                                        ->orWhereHas('transportContractor', fn (Builder $relationQuery): Builder => $relationQuery->where('name', 'like', '%'.$value.'%'));
                                }),
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
                            fn (Builder $query, $value): Builder => $query->where('driver_name', 'like', '%'.$value.'%'),
                        );
                    }),

                Tables\Filters\TernaryFilter::make('pilot_funds_paid')
                    ->label('Wypłata pilotowi')
                    ->visible(fn (): bool => Schema::hasColumn('events', 'pilot_funds_paid'))
                    ->placeholder('Wszystkie')
                    ->trueLabel('Wypłacono')
                    ->falseLabel('Do wypłaty')
                    ->queries(
                        true: fn (Builder $query) => $query->where('pilot_funds_paid', true),
                        false: fn (Builder $query) => $query
                            ->where('pilot_funds_paid', false)
                            ->whereNotNull('assigned_to'),
                        blank: fn (Builder $query) => $query,
                    ),

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
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('open_program')
                        ->label('Program')
                        ->icon('heroicon-o-list-bullet')
                        ->url(fn (Event $record): string => static::getUrl('edit-program', ['record' => $record])),
                    Tables\Actions\Action::make('open_participants')
                        ->label('Uczestnicy')
                        ->icon('heroicon-o-users')
                        ->url(fn (Event $record): string => static::getUrl('participants', ['record' => $record]))
                        ->visible(fn (): bool => Schema::hasTable('event_participants')),
                    Tables\Actions\Action::make('open_finance')
                        ->label('Finanse')
                        ->icon('heroicon-o-banknotes')
                        ->url(fn (Event $record): string => static::getUrl('calculation', ['record' => $record])),
                    Tables\Actions\Action::make('open_pilot')
                        ->label('Pilot')
                        ->icon('heroicon-o-user-circle')
                        ->url(fn (Event $record): string => static::getUrl('pilot', ['record' => $record])),
                    Tables\Actions\Action::make('quick_pilot')
                        ->label('Szybki pilot')
                        ->icon('heroicon-o-pencil-square')
                        ->slideOver()
                        ->modalHeading('Przypisz pilota')
                        ->modalWidth('md')
                        ->fillForm(fn (Event $record): array => [
                            'assigned_to' => $record->assigned_to,
                            'shared_with_pilot' => (bool) ($record->shared_with_pilot ?? false),
                        ])
                        ->form([
                            Forms\Components\Select::make('assigned_to')
                                ->label('Pilot / opiekun')
                                ->relationship('assignedUser', 'name')
                                ->searchable()
                                ->preload()
                                ->nullable(),
                            Forms\Components\Toggle::make('shared_with_pilot')
                                ->label('Udostępnij w panelu pilota')
                                ->helperText('Impreza widoczna u pilota dopiero po udostępnieniu.')
                                ->visible(fn (): bool => Schema::hasColumn('events', 'shared_with_pilot')),
                        ])
                        ->action(function (Event $record, array $data): void {
                            $payload = [
                                'assigned_to' => $data['assigned_to'] ?? null,
                            ];

                            if (Schema::hasColumn('events', 'shared_with_pilot')) {
                                $payload['shared_with_pilot'] = (bool) ($data['shared_with_pilot'] ?? false);
                            }

                            $previousPilot = $record->assigned_to;
                            $record->fill($payload);

                            if (
                                Schema::hasColumn('events', 'shared_with_pilot')
                                && $previousPilot
                                && (int) $previousPilot !== (int) ($payload['assigned_to'] ?? 0)
                            ) {
                                $record->shared_with_pilot = false;
                            }

                            $record->save();
                        }),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (Event $record) => $record->status === Event::STATUS_INQUIRY),
                ]),
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
            ->recordUrl(fn (Event $record): string => static::getUrl('edit', ['record' => $record]))
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        $items = [
            Pages\EditEvent::class,
            Pages\EditEventProgram::class,
            Pages\ManageEventTasks::class,
        ];

        if (Schema::hasTable('event_participants')) {
            $items[] = Pages\ManageEventParticipants::class;
        }

        $items[] = Pages\ManageEventReservations::class;
        $items[] = Pages\ManageEventTransport::class;
        $items[] = Pages\EventHotelPlanning::class;
        $items[] = Pages\ManageEventPilot::class;

        if (Schema::hasTable('contracts') || Schema::hasTable('event_agreements')) {
            $items[] = Pages\ManageEventContracts::class;
        }

        $items[] = Pages\EventFinance::class;

        if (Schema::hasTable('event_day_insurance')) {
            $items[] = Pages\ManageEventDayInsurances::class;
        }

        if (Schema::hasTable('event_documents')) {
            $items[] = Pages\ManageEventDocuments::class;
        }

        return $page->generateNavigationItems($items);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
            'edit-program' => Pages\EditEventProgram::route('/{record}/program'),
            'calculation' => Pages\EventCalculation::route('/{record}/calculation'),
            'tasks' => Pages\ManageEventTasks::route('/{record}/tasks'),
            'resignations' => Pages\RedirectLegacyEventResignations::route('/{record}/resignations'),
            'transport' => Pages\ManageEventTransport::route('/{record}/transport'),
            'hotel-planning' => Pages\EventHotelPlanning::route('/{record}/hotel-planning'),
            'pilot' => Pages\ManageEventPilot::route('/{record}/pilot'),
            'client-portal' => Pages\RedirectLegacyEventClientPortal::route('/{record}/portal-klienta'),
            'finance' => Pages\EventFinance::route('/{record}/finance'),
            'day-insurances' => Pages\ManageEventDayInsurances::route('/{record}/day-insurances'),
            'reservations' => Pages\ManageEventReservations::route('/{record}/reservations'),
            'contracts' => Pages\ManageEventContracts::route('/{record}/contracts'),
            'participants' => Pages\ManageEventParticipants::route('/{record}/participants'),
            'participant-payments' => Pages\ManageEventSettlementPayments::route('/{record}/participants/payments'),
            'participant-resignations' => Pages\ManageEventResignations::route('/{record}/participants/resignations'),
            'participant-portal' => Pages\ManageEventClientPortal::route('/{record}/participants/portal'),
            'documents' => Pages\ManageEventDocuments::route('/{record}/documents'),
            'settlement-summary' => Pages\ManageEventSettlementSummary::route('/{record}/settlement'),
            'settlement-costs' => Pages\ManageEventSettlementCosts::route('/{record}/settlement/costs'),
            'settlement-payments' => Pages\RedirectLegacyEventSettlementPayments::route('/{record}/settlement/payments'),
            'settlement-pilot-cash' => Pages\ManageEventSettlementPilotCash::route('/{record}/settlement/pilot-cash'),
            'settlement-currency-exchanges' => Pages\ManageEventSettlementCurrencyExchanges::route('/{record}/settlement/currency-exchanges'),
            'settlement-documents' => Pages\ManageEventSettlementDocuments::route('/{record}/settlement/documents'),
            'vendor-invoices' => Pages\RedirectLegacyEventVendorInvoices::route('/{record}/vendor-invoices'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return \Illuminate\Support\Facades\Cache::remember('event_resource_nav_badge', 60, function (): ?string {
            // Legacy DB: `in_progress`; bieżący model: `to_settle` (do rozliczenia).
            $count = static::getModel()::query()
                ->whereIn('status', [Event::STATUS_TO_SETTLE, 'in_progress'])
                ->count();

            return $count > 0 ? (string) $count : null;
        });
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! Schema::hasTable('contracts') && ! Schema::hasTable('event_agreements')) {
            if (Schema::hasTable('event_day_insurance')) {
                $query->withCount([
                    'dayInsurances as day_insurances_count' => fn (Builder $query) => $query->whereNotNull('insurance_id'),
                ]);
            }

            $relations = [
                'eventTemplate',
                'startPlace',
                'assignedUser',
                'transportContractor',
                'hotelProgramPoints',
                'activeSettlement.participantPayments',
                'bus:id,name',
                'markup:id,percent',
            ];

            if (Schema::hasTable('event_contractor')) {
                $relations[] = 'orderingContractors';
            }

            if (Schema::hasColumn('events', 'pilot_funds_paid')) {
                $relations[] = 'pilotFundsPaidByUser';
            }

            return $query
                ->with(['qtyVariants:id,event_id,qty,gratis'])
                ->with($relations);
        }

        $withCount = [
            'agreements',
            'agreements as agreements_paid_count' => fn (Builder $query) => $query->where('payment_status', 'paid'),
        ];

        if (Schema::hasTable('event_day_insurance')) {
            $withCount['dayInsurances as day_insurances_count'] = fn (Builder $query) => $query->whereNotNull('insurance_id');
        }

        $query->withCount($withCount)->withSum([
            'agreements as paid_participants_count' => fn (Builder $query) => $query->where('payment_status', 'paid'),
        ], 'participant_count')->withSum('agreements as agreements_amount_paid_total', 'amount_paid')
            ->with(['qtyVariants:id,event_id,qty,gratis']);

        $relations = [
            'eventTemplate',
            'startPlace',
            'assignedUser',
            'transportContractor',
            'hotelProgramPoints',
            'activeSettlement.participantPayments',
            'bus:id,name',
            'markup:id,percent',
        ];

        if (Schema::hasTable('event_contractor')) {
            $relations[] = 'orderingContractors';
        }

        if (Schema::hasColumn('events', 'pilot_funds_paid')) {
            $relations[] = 'pilotFundsPaidByUser';
        }

        return $query->with($relations);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['admin', 'super_admin', 'biuro', 'ksiegowosc'])) {
            return true;
        }

        if ($user->can('view event')) {
            return true;
        }

        return true;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['admin', 'super_admin'])) {
            return true;
        }

        return $user->can('create event');
    }
}
