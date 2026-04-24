<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Filament\Resources\ReservationResource;
use App\Filament\Resources\TaskResource;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventTemplateProgramPoint;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ProgramPointsRelationManager extends RelationManager
{
    protected static string $relationship = 'programPoints';

    protected static ?string $title = 'Program imprezy';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('event_template_program_point_id')
                    ->label('Punkt programu')
                    ->relationship('templatePoint', 'name')
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                        if (blank($state) || filled($get('name'))) {
                            return;
                        }

                        $templatePoint = EventTemplateProgramPoint::find($state);
                        if ($templatePoint) {
                            $set('name', $templatePoint->name);
                        }
                    })
                    ->required(),

                Forms\Components\TextInput::make('name')
                    ->label('Nazwa punktu programu')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Możesz nadać własną nazwę dla tej imprezy, np. Parking Wieliczka.'),

                Forms\Components\Select::make('contractor_id')
                    ->label('Wykonawca/Kontraktor')
                    ->relationship('contractor', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Forms\Components\TextInput::make('day')
                    ->label('Dzień')
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                Forms\Components\TextInput::make('order')
                    ->label('Kolejność')
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                Forms\Components\RichEditor::make('description')
                    ->label('Opis punktu programu (dla tej imprezy)')
                    ->columnSpanFull()
                    ->toolbarButtons($this->getProgramPointEditorToolbarButtons()),

                Forms\Components\Select::make('parent_id')
                    ->label('Punkt nadrzędny')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->options(function (?EventProgramPoint $record) {
                        return $this->getOwnerRecord()
                            ->programPoints()
                            ->with('templatePoint')
                            ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                            ->orderBy('day')
                            ->orderBy('order')
                            ->get()
                            ->mapWithKeys(fn (EventProgramPoint $point) => [
                                $point->id => sprintf(
                                    'Dzień %d • %02d. %s',
                                    (int) ($point->day ?? 1),
                                    (int) ($point->order ?? 1),
                                    $point->name ?? $point->templatePoint?->name ?? ('Punkt #'.$point->id)
                                ),
                            ]);
                    }),

                Forms\Components\TimePicker::make('start_time')
                    ->label('Godzina startu')
                    ->seconds(false)
                    ->native(false)
                    ->nullable()
                    ->rule('required_with:end_time'),

                Forms\Components\TimePicker::make('end_time')
                    ->label('Godzina końca')
                    ->seconds(false)
                    ->native(false)
                    ->nullable()
                    ->rule('required_with:start_time')
                    ->rule('after:start_time'),

                Forms\Components\TextInput::make('unit_price')
                    ->label('Cena jednostkowa (planowana)')
                    ->numeric()
                    ->prefix('PLN')
                    ->step(0.01)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                        $quantity = max(1, (int) ($get('quantity') ?: 1));
                        $set('total_price', round((float) ($state ?: 0) * $quantity, 2));
                        $set('planned_price', round((float) ($state ?: 0) * $quantity, 2));
                    }),

                Forms\Components\TextInput::make('calculated_price')
                    ->label('Cena kalkulacji (wg szablonu)')
                    ->numeric()
                    ->prefix('PLN')
                    ->readOnly()
                    ->helperText('Wyliczana automatycznie na podstawie szablonu'),

                Forms\Components\TextInput::make('planned_price')
                    ->label('Cena planowana (możesz zmienić)')
                    ->numeric()
                    ->prefix('PLN')
                    ->helperText('Możesz nadpisać cenę kalkulacji'),

                Forms\Components\TextInput::make('paid_price')
                    ->label('Cena zapłacona (rozliczenie)')
                    ->numeric()
                    ->prefix('PLN')
                    ->readOnly()
                    ->helperText('Ustalana w rozliczeniu'),

                Forms\Components\TextInput::make('quantity')
                    ->label('Ilość')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                        $unitPrice = (float) ($get('unit_price') ?: 0);
                        $quantity = max(1, (int) ($state ?: 1));
                        $set('total_price', round($unitPrice * $quantity, 2));
                    }),

                Forms\Components\TextInput::make('group_size')
                    ->label('Wielkość grupy')
                    ->numeric()
                    ->minValue(1)
                    ->nullable()
                    ->helperText('Jeśli ilość = 1, system może wyliczyć ilość z liczby uczestników i wielkości grupy.')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                        $currentQuantity = max(1, (int) ($get('quantity') ?: 1));
                        $groupSize = (int) ($state ?: 0);

                        if ($groupSize <= 0 || $currentQuantity > 1) {
                            return;
                        }

                        $participants = max(1, (int) ($this->getOwnerRecord()->participant_count ?? 1));
                        $calculatedQuantity = max(1, (int) ceil($participants / $groupSize));
                        $unitPrice = (float) ($get('unit_price') ?: 0);

                        $set('quantity', $calculatedQuantity);
                        $set('total_price', round($unitPrice * $calculatedQuantity, 2));
                    }),

                Forms\Components\TextInput::make('total_price')
                    ->label('Cena całkowita (PLN)')
                    ->numeric()
                    ->prefix('PLN')
                    ->step(0.01)
                    ->readOnly()
                    ->helperText('Obliczane automatycznie'),

                Forms\Components\Placeholder::make('reservations_preview')
                    ->label('Rezerwacje dla punktu programu')
                    ->visible(fn (?EventProgramPoint $record): bool => filled($record))
                    ->content(function (?EventProgramPoint $record): HtmlString {
                        if (! $record) {
                            return new HtmlString('');
                        }

                        $reservations = $record->reservations()
                            ->latest('reserved_at')
                            ->get();

                        if ($reservations->isEmpty()) {
                            return new HtmlString('<div class="text-sm text-gray-500">Brak rezerwacji dla tego punktu programu.</div>');
                        }

                        $items = $reservations->map(function ($reservation) {
                            $label = e($reservation->booking_reference ?: ('Rezerwacja #'.$reservation->id));
                            $status = e(\App\Models\Reservation::$statuses[$reservation->status] ?? $reservation->status);
                            $amount = $reservation->reserved_amount !== null
                                ? number_format((float) $reservation->reserved_amount, 2, ',', ' ').' PLN'
                                : 'brak kwoty';
                            $url = ReservationResource::getUrl('edit', ['record' => $reservation]);

                            return "<li><a href=\"{$url}\" class=\"text-primary-600 hover:underline\" target=\"_blank\">{$label}</a> <span class=\"text-gray-500\">({$status}, {$amount})</span></li>";
                        })->implode('');

                        return new HtmlString("<ul class=\"list-disc pl-5 space-y-1 text-sm\">{$items}</ul>");
                    })
                    ->columnSpanFull(),

                Forms\Components\RichEditor::make('notes')
                    ->label('Uwagi dla kierowcy / ogólne')
                    ->columnSpanFull()
                    ->toolbarButtons($this->getProgramPointEditorToolbarButtons()),

                Forms\Components\RichEditor::make('office_notes')
                    ->label('Uwagi dla biura')
                    ->columnSpanFull()
                    ->toolbarButtons($this->getProgramPointEditorToolbarButtons()),

                Forms\Components\RichEditor::make('pilot_notes')
                    ->label('Uwagi dla pilota')
                    ->columnSpanFull()
                    ->toolbarButtons($this->getProgramPointEditorToolbarButtons()),

                Forms\Components\Toggle::make('include_in_program')
                    ->label('Uwzględnij w programie')
                    ->default(true),

                Forms\Components\Toggle::make('include_in_calculation')
                    ->label('Uwzględnij w kalkulacji')
                    ->default(true),

                Forms\Components\Toggle::make('active')
                    ->label('Aktywny')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('order')
            ->deferLoading()
            ->defaultSort('order')
            ->recordAction('edit')
            ->groups([
                Tables\Grouping\Group::make('day')
                    ->label('Dzień')
                    ->getTitleFromRecordUsing(fn ($record) => 'Dzień '.$record->day)
                    ->collapsible(false) // Nie pozwalamy na zwijanie
                    ->orderQueryUsing(fn ($query, string $direction) => $query->orderBy('day', $direction)),
            ])
            ->defaultGroup('day')
            ->columns([
                Tables\Columns\TextColumn::make('order')
                    ->label('Kolejność')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('templatePoint.name')
                    ->label('Punkt programu')
                    ->html()
                    ->state(function (EventProgramPoint $record): string {
                        $name = e($record->name ?? $record->templatePoint?->name ?? '—');
                        $start = $record->start_time ? substr((string) $record->start_time, 0, 5) : null;
                        $end = $record->end_time ? substr((string) $record->end_time, 0, 5) : null;
                        $time = ($start && $end) ? "{$start} - {$end}" : '—';

                        $contractorName = $record->contractor?->name
                            ?? $record->reservations->sortByDesc('reserved_at')->first()?->contractor?->name
                            ?? '—';
                        $contractorPhone = $record->contractor?->phone
                            ?? $record->reservations->sortByDesc('reserved_at')->first()?->contractor?->phone
                            ?? '—';

                        $reservation = $record->reservations->sortByDesc('reserved_at')->first();
                        if ($reservation) {
                            $reservationLabel = e($reservation->booking_reference ?: ('#'.$reservation->id));
                            $reservationStatus = e(\App\Models\Reservation::$statuses[$reservation->status] ?? $reservation->status);
                            $reservationAmount = $reservation->reserved_amount !== null
                                ? number_format((float) $reservation->reserved_amount, 2, ',', ' ').' PLN'
                                : 'brak kwoty';
                            $reservationText = "{$reservationLabel} • {$reservationStatus} • {$reservationAmount}";
                        } else {
                            $reservationText = 'brak';
                        }

                        return "<div class='space-y-1'>"
                            ."<div class='text-xs text-gray-500'>{$time}</div>"
                            ."<div class='font-bold text-gray-900'>{$name}</div>"
                            ."<div class='text-xs text-gray-600'>Kontrahent: ".e($contractorName).', tel.: '.e($contractorPhone).'</div>'
                            ."<div class='text-xs text-gray-600'>Rezerwacja: ".e($reservationText).'</div>'
                            .'</div>';
                    })
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->whereHas('templatePoint', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                                ->orWhere('event_program_points.name', 'like', "%{$search}%")
                                ->orWhereHas('contractor', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('reservations', fn ($q) => $q->where('booking_reference', 'like', "%{$search}%"));
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->leftJoin('event_template_program_points as sortable_template_points', 'sortable_template_points.id', '=', 'event_program_points.event_template_program_point_id')
                            ->orderByRaw("COALESCE(sortable_template_points.name, event_program_points.name) {$direction}")
                            ->select('event_program_points.*');
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('prices_summary')
                    ->label('Ceny')
                    ->state(function (EventProgramPoint $record): string {
                        $calc = number_format((float) ($record->calculated_price ?? 0), 2, ',', ' ');
                        $plan = number_format((float) ($record->planned_price ?? 0), 2, ',', ' ');
                        $paid = number_format((float) ($record->paid_price ?? 0), 2, ',', ' ');

                        return "<div><span style='font-size:11px;color:#888'>Kalkulacja:</span> <b>{$calc} PLN</b><br>"
                            ."<span style='font-size:11px;color:#888'>Planowana:</span> <b>{$plan} PLN</b><br>"
                            ."<span style='font-size:11px;color:#888'>Zapłacona:</span> <b>{$paid} PLN</b></div>";
                    })
                    ->html()
                    ->alignEnd(),

                Tables\Columns\ViewColumn::make('notes_preview')
                    ->label('Uwagi')
                    ->view('filament.components.program-point-notes-preview'),

                Tables\Columns\TextColumn::make('flags')
                    ->label('Status')
                    ->html()
                    ->state(function (EventProgramPoint $record): string {
                        $line = static fn (string $label, bool $state): string => sprintf(
                            '<div class="text-xs font-semibold %s">%s</div>',
                            $state ? 'text-green-600' : 'text-red-600',
                            e($label)
                        );

                        return '<div class="space-y-1">'
                            .$line('Program', (bool) $record->include_in_program)
                            .$line('Kalkulacja', (bool) $record->include_in_calculation)
                            .$line('Aktywny', (bool) $record->active)
                            .'</div>';
                    })
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('day')
                    ->label('Dzień')
                    ->options(function () {
                        $maxDay = (int) ($this->getOwnerRecord()->duration_days ?? 1);
                        $options = [];
                        for ($i = 1; $i <= $maxDay; $i++) {
                            $options[$i] = "Dzień {$i}";
                        }

                        return $options;
                    }),

                Tables\Filters\SelectFilter::make('contractor_id')
                    ->label('Kontrahent')
                    ->relationship('contractor', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('price_range')
                    ->label('Kwota')
                    ->form([
                        Forms\Components\TextInput::make('price_from')
                            ->label('Od')
                            ->numeric()
                            ->suffix('PLN'),
                        Forms\Components\TextInput::make('price_to')
                            ->label('Do')
                            ->numeric()
                            ->suffix('PLN'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['price_from'] ?? null, fn (Builder $q, $value) => $q->where('total_price', '>=', (float) $value))
                            ->when($data['price_to'] ?? null, fn (Builder $q, $value) => $q->where('total_price', '<=', (float) $value));
                    }),

                Tables\Filters\Filter::make('zero_price')
                    ->label('Tylko 0 PLN')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $subQuery) {
                        $subQuery
                            ->whereNull('total_price')
                            ->orWhere('total_price', '<=', 0);
                    })),

                Tables\Filters\Filter::make('program_only')
                    ->label('Tylko program')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('include_in_program', true)
                        ->where('include_in_calculation', false)),

                Tables\Filters\TernaryFilter::make('include_in_program')
                    ->label('Uwzględniony w programie'),

                Tables\Filters\TernaryFilter::make('include_in_calculation')
                    ->label('Uwzględniony w kalkulacji'),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Aktywny'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('time_planner')
                    ->label('Planner czasu')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->url(fn (): string => \App\Filament\Resources\EventResource::getUrl('edit-program', [
                        'record' => $this->getOwnerRecord()->id,
                    ])),

                Tables\Actions\Action::make('add_program_point')
                    ->label('Dodaj punkt programu')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->modalHeading('Dodaj punkt programu do imprezy')
                    ->modalDescription('Wyszukaj w szablonach lub istniejących punktach innych imprez, albo utwórz nowy.')
                    ->modalWidth('7xl')
                    ->modalSubmitActionLabel('Dodaj punkt')
                    ->modalCancelActionLabel('Anuluj')
                    ->form([
                        Forms\Components\Select::make('source_point')
                            ->label('Szukaj w szablonach i punktach innych imprez (opcjonalne)')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->options(function () {
                                $templatePoints = EventTemplateProgramPoint::all()->mapWithKeys(function ($point) {
                                    $label = '[Szablon] '.$point->name;

                                    return ['template_'.$point->id => $label];
                                });
                                $eventPoints = EventProgramPoint::whereNull('event_id')->get()->mapWithKeys(function ($point) {
                                    // Punkty bez event_id nie powinny istnieć, więc pobierzmy z innych imprez
                                    return [];
                                });
                                $otherEventPoints = EventProgramPoint::whereNotNull('event_id')->with('event')->get()->mapWithKeys(function ($point) {
                                    $eventName = $point->event?->name ?? ('Impreza #'.$point->event_id);
                                    $label = '[Inna impreza] '.$point->name.' ('.$eventName.')';

                                    return ['event_'.$point->id => $label];
                                });

                                return $templatePoints->all() + $otherEventPoints->all();
                            })
                            ->allowHtml()
                            ->placeholder('Wpisz aby szukać w szablonach lub punktach innych imprez...')
                            ->helperText('Opcjonalne — zostaw puste, aby stworzyć nowy punkt specyficzny dla tej imprezy')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    if (str_starts_with($state, 'template_')) {
                                        $id = (int) str_replace('template_', '', $state);
                                        $templatePoint = EventTemplateProgramPoint::find($id);
                                        if ($templatePoint) {
                                            $set('name', $templatePoint->name);
                                            $set('description', $templatePoint->description);
                                            $set('unit_price', $templatePoint->unit_price);
                                            $set('group_size', $templatePoint->group_size);
                                        }
                                    } elseif (str_starts_with($state, 'event_')) {
                                        $id = (int) str_replace('event_', '', $state);
                                        $eventPoint = EventProgramPoint::find($id);
                                        if ($eventPoint) {
                                            $set('name', $eventPoint->name);
                                            $set('description', $eventPoint->description);
                                            $set('unit_price', $eventPoint->unit_price);
                                            $set('group_size', $eventPoint->group_size);
                                        }
                                    }
                                } else {
                                    $set('name', '');
                                    $set('description', '');
                                }
                            }),

                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa punktu programu')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('np. Zwiedzanie muzeum, Transfer na lotnisko...')
                            ->helperText('Zostanie automatycznie wypełniona przy wyborze z biblioteki'),

                        Forms\Components\RichEditor::make('description')
                            ->label('Opis punktu programu (dla tej imprezy)')
                            ->toolbarButtons($this->getProgramPointEditorToolbarButtons())
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('day')
                                    ->label('Dzień')
                                    ->options(function () {
                                        $maxDay = (int) ($this->getOwnerRecord()->duration_days ?? 1);
                                        $options = [];
                                        for ($i = 1; $i <= $maxDay; $i++) {
                                            $options[$i] = "Dzień {$i}";
                                        }

                                        return $options;
                                    })
                                    ->default(1)
                                    ->required(),

                                Forms\Components\TextInput::make('order')
                                    ->label('Kolejność')
                                    ->numeric()
                                    ->default(function () {
                                        return $this->getOwnerRecord()
                                            ->programPoints()
                                            ->max('order') + 1;
                                    })
                                    ->required(),
                            ]),

                        Forms\Components\Select::make('parent_id')
                            ->label('Punkt nadrzędny (opcjonalnie)')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->options(function () {
                                return $this->getOwnerRecord()
                                    ->programPoints()
                                    ->with('templatePoint')
                                    ->orderBy('day')
                                    ->orderBy('order')
                                    ->get()
                                    ->mapWithKeys(fn (EventProgramPoint $point) => [
                                        $point->id => sprintf(
                                            'Dzień %d • %02d. %s',
                                            (int) ($point->day ?? 1),
                                            (int) ($point->order ?? 1),
                                            $point->name ?? $point->templatePoint?->name ?? ('Punkt #'.$point->id)
                                        ),
                                    ]);
                            }),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Cena jednostkowa (PLN)')
                                    ->numeric()
                                    ->prefix('PLN')
                                    ->step(0.01)
                                    ->helperText('Zostanie automatycznie wypełniona z szablonu'),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Ilość')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                        $unitPrice = (float) ($get('unit_price') ?: 0);
                                        $quantity = max(1, (int) ($state ?: 1));
                                        $set('total_price_preview', round($unitPrice * $quantity, 2));
                                    }),

                                Forms\Components\TextInput::make('group_size')
                                    ->label('Wielkość grupy')
                                    ->numeric()
                                    ->minValue(1)
                                    ->nullable()
                                    ->helperText('Liczba osób w grupie')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                        $currentQuantity = max(1, (int) ($get('quantity') ?: 1));
                                        $groupSize = (int) ($state ?: 0);

                                        if ($groupSize <= 0 || $currentQuantity > 1) {
                                            return;
                                        }

                                        $participants = max(1, (int) ($this->getOwnerRecord()->participant_count ?? 1));
                                        $calculatedQuantity = max(1, (int) ceil($participants / $groupSize));
                                        $unitPrice = (float) ($get('unit_price') ?: 0);

                                        $set('quantity', $calculatedQuantity);
                                        $set('total_price_preview', round($unitPrice * $calculatedQuantity, 2));
                                    }),

                                Forms\Components\TextInput::make('total_price_preview')
                                    ->label('Cena całkowita (podgląd)')
                                    ->numeric()
                                    ->prefix('PLN')
                                    ->default(0)
                                    ->readOnly()
                                    ->dehydrated(false),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TimePicker::make('start_time')
                                    ->label('Godzina startu')
                                    ->seconds(false)
                                    ->native(false)
                                    ->nullable()
                                    ->rule('required_with:end_time'),

                                Forms\Components\TimePicker::make('end_time')
                                    ->label('Godzina końca')
                                    ->seconds(false)
                                    ->native(false)
                                    ->nullable()
                                    ->rule('required_with:start_time')
                                    ->rule('after:start_time'),
                            ]),

                        Forms\Components\RichEditor::make('notes')
                            ->label('Uwagi specjalne dla tej imprezy')
                            ->placeholder('Dodatkowe uwagi specyficzne dla tej imprezy...')
                            ->toolbarButtons($this->getProgramPointEditorToolbarButtons())
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('office_notes')
                            ->label('Uwagi dla biura')
                            ->toolbarButtons($this->getProgramPointEditorToolbarButtons())
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('pilot_notes')
                            ->label('Uwagi dla pilota')
                            ->toolbarButtons($this->getProgramPointEditorToolbarButtons())
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('include_in_program')
                                    ->label('Uwzględnij w programie')
                                    ->default(true),

                                Forms\Components\Toggle::make('include_in_calculation')
                                    ->label('Uwzględnij w kalkulacji')
                                    ->default(true),

                                Forms\Components\Toggle::make('active')
                                    ->label('Aktywny')
                                    ->default(true),
                            ]),
                    ])
                    ->action(function (array $data) {
                        $source = $data['source_point'] ?? null;
                        $templatePoint = null;
                        $eventPoint = null;
                        if ($source && str_starts_with($source, 'template_')) {
                            $id = (int) str_replace('template_', '', $source);
                            $templatePoint = EventTemplateProgramPoint::find($id);
                        } elseif ($source && str_starts_with($source, 'event_')) {
                            $id = (int) str_replace('event_', '', $source);
                            $eventPoint = EventProgramPoint::find($id);
                        }

                        $unitPrice = (float) ($data['unit_price'] ?? ($templatePoint?->unit_price ?? $eventPoint?->unit_price ?? 0));
                        $groupSize = (int) ($data['group_size'] ?? $templatePoint?->group_size ?? $eventPoint?->group_size ?? 0);
                        $quantity = $this->resolveQuantityForProgramPoint(
                            (int) ($data['quantity'] ?? 1),
                            $groupSize
                        );

                        // Klonowanie z szablonu lub innej imprezy
                        if ($templatePoint) {
                            $newPoint = $this->getOwnerRecord()->programPoints()->create([
                                'event_template_program_point_id' => $templatePoint->id,
                                'name' => $data['name'] ?? $templatePoint->name,
                                'description' => $data['description'] ?? $templatePoint->description ?? null,
                                'day' => $data['day'],
                                'order' => $data['order'],
                                'parent_id' => $data['parent_id'] ?? null,
                                'start_time' => $data['start_time'] ?? null,
                                'end_time' => $data['end_time'] ?? null,
                                'unit_price' => $unitPrice,
                                'quantity' => $quantity,
                                'total_price' => $unitPrice * $quantity,
                                'group_size' => $data['group_size'] ?? $templatePoint->group_size ?? null,
                                'notes' => $data['notes'] ?? null,
                                'office_notes' => $data['office_notes'] ?? $templatePoint->office_notes ?? null,
                                'pilot_notes' => $data['pilot_notes'] ?? $templatePoint->pilot_notes ?? null,
                                'include_in_program' => $data['include_in_program'],
                                'include_in_calculation' => $data['include_in_calculation'],
                                'active' => $data['active'],
                            ]);
                            // Rekurencyjne kopiowanie podpunktów z szablonu
                            $cloneChildren = function ($templateParent, $eventParentId) use (&$cloneChildren, $newPoint) {
                                foreach ($templateParent->children as $child) {
                                    $cloned = $newPoint->event->programPoints()->create([
                                        'event_template_program_point_id' => $child->id,
                                        'name' => $child->name,
                                        'description' => $child->description,
                                        'day' => $newPoint->day,
                                        'order' => $child->order,
                                        'parent_id' => $eventParentId,
                                        'unit_price' => $child->unit_price,
                                        'quantity' => $child->group_size ?? 1,
                                        'total_price' => $child->unit_price * ($child->group_size ?? 1),
                                        'group_size' => $child->group_size,
                                        'notes' => $child->notes,
                                        'office_notes' => $child->office_notes,
                                        'pilot_notes' => $child->pilot_notes,
                                        'include_in_program' => $child->include_in_program,
                                        'include_in_calculation' => $child->include_in_calculation,
                                        'active' => $child->active,
                                    ]);
                                    $cloneChildren($child, $cloned->id);
                                }
                            };
                            $cloneChildren($templatePoint, $newPoint->id);
                        } elseif ($eventPoint) {
                            $cloneRecursive = function ($sourcePoint, $eventId, $parentId = null, $day = null) use (&$cloneRecursive, $data) {
                                $cloned = $sourcePoint->replicate();
                                $cloned->event_id = $eventId;
                                $cloned->parent_id = $parentId;
                                $cloned->day = $day ?? $data['day'];
                                $cloned->order = $sourcePoint->order;
                                $cloned->save();
                                foreach ($sourcePoint->children as $child) {
                                    $cloneRecursive($child, $eventId, $cloned->id, $cloned->day);
                                }

                                return $cloned;
                            };
                            $cloneRecursive($eventPoint, $this->getOwnerRecord()->id, $data['parent_id'] ?? null, $data['day']);
                        } else {
                            // Nowy punkt od zera
                            $this->getOwnerRecord()->programPoints()->create([
                                'name' => $data['name'],
                                'description' => $data['description'] ?? null,
                                'day' => $data['day'],
                                'order' => $data['order'],
                                'parent_id' => $data['parent_id'] ?? null,
                                'start_time' => $data['start_time'] ?? null,
                                'end_time' => $data['end_time'] ?? null,
                                'unit_price' => $unitPrice,
                                'quantity' => $quantity,
                                'total_price' => $unitPrice * $quantity,
                                'group_size' => $data['group_size'] ?? null,
                                'notes' => $data['notes'] ?? null,
                                'office_notes' => $data['office_notes'] ?? null,
                                'pilot_notes' => $data['pilot_notes'] ?? null,
                                'include_in_program' => $data['include_in_program'],
                                'include_in_calculation' => $data['include_in_calculation'],
                                'active' => $data['active'],
                            ]);
                        }
                    }),

                Tables\Actions\Action::make('copy_from_template')
                    ->label('Skopiuj z szablonu')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->action(function () {
                        $event = $this->getOwnerRecord();
                        $event->copyProgramPointsFromTemplate();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Skopiuj program z szablonu')
                    ->modalDescription('To działanie skopiuje wszystkie punkty programu z szablonu. Istniejące punkty zostaną zastąpione.')
                    ->visible(fn () => $this->getOwnerRecord()->programPoints()->count() === 0),
            ])
            ->actions([
                Tables\Actions\Action::make('normalize_legacy_price')
                    ->label('Napraw kwotę')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->button()
                    ->extraAttributes(['class' => 'w-full'])
                    ->requiresConfirmation()
                    ->modalHeading('Naprawić kwotę legacy?')
                    ->modalDescription('Przeliczy ilość i kwotę punktu na podstawie uczestników imprezy, aby nie był traktowany jako pojedynczy wydatek.')
                    ->action(function (EventProgramPoint $record): void {
                        if ($this->normalizeLegacyPointPricing($record)) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Kwota punktu naprawiona')
                                ->send();

                            return;
                        }

                        \Filament\Notifications\Notification::make()
                            ->info()
                            ->title('Brak zmian')
                            ->body('Punkt nie wygląda na legacy lub jest już poprawnie wyliczony.')
                            ->send();
                    })
                    ->visible(fn (EventProgramPoint $record): bool => $this->isLegacySingleUnitPoint($record)),

                Tables\Actions\Action::make('create_task')
                    ->label('Dodaj zadanie')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('primary')
                    ->button()
                    ->extraAttributes(['class' => 'w-full'])
                    ->url(fn (EventProgramPoint $record): string => TaskResource::getUrl('create', [
                        'taskable_type' => EventProgramPoint::class,
                        'taskable_id' => $record->getKey(),
                    ]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('settle_point')
                    ->label('Rozlicz')
                    ->icon('heroicon-o-receipt-percent')
                    ->color('warning')
                    ->button()
                    ->extraAttributes(['class' => 'w-full'])
                    ->modalHeading('Rozlicz punkt programu')
                    ->modalDescription('Rozliczysz punkt bez wychodzenia z programu imprezy. Dane planowane są widoczne poniżej.')
                    ->modalWidth('2xl')
                    ->fillForm(function (EventProgramPoint $record): array {
                        $event = $this->getOwnerRecord();
                        $participantCount = max(1, (int) ($event->participant_count ?? 1));
                        $calculatedQuantity = $record->resolveCalculatedQuantity($participantCount);
                        $plannedTotal = $record->resolveEffectiveTotalPrice($participantCount);

                        $existingCost = $event->settlements()
                            ->whereIn('status', ['draft', 'active', 'pilot_settled'])
                            ->latest('id')
                            ->first()?->costs()
                            ->where('source_type', 'program_point')
                            ->where('source_id', $record->id)
                            ->first();

                        return [
                            'planned_unit_price' => (float) ($record->unit_price ?? 0),
                            'planned_group_size' => (int) ($record->group_size ?? 0),
                            'planned_quantity' => $calculatedQuantity,
                            'planned_total' => $plannedTotal,
                            'payment_status' => $existingCost?->payment_status ?? 'planned',
                            'advance_type' => $existingCost?->advance_type ?? 'full',
                            'advance_amount' => $existingCost?->advance_amount,
                            'advance_due_date' => $existingCost?->advance_due_date,
                            'actual_amount' => $existingCost?->actual_amount,
                            'payment_method' => $existingCost?->payment_method,
                            'document_number' => $existingCost?->document_number,
                            'paid_at' => $existingCost?->paid_at,
                            'notes' => $existingCost?->notes,
                        ];
                    })
                    ->form([
                        Forms\Components\Section::make('Dane punktu programu')
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('planned_unit_price')
                                    ->label('Cena jednostkowa')
                                    ->numeric()
                                    ->suffix('PLN')
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make('planned_group_size')
                                    ->label('Wielkość grupy')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('—'),

                                Forms\Components\TextInput::make('planned_quantity')
                                    ->label('Ilość sztuk')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make('planned_total')
                                    ->label('Cena całkowita (plan)')
                                    ->numeric()
                                    ->suffix('PLN')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),

                        Forms\Components\Section::make('Rozliczenie kosztu')
                            ->columns(2)
                            ->schema([
                                Forms\Components\Select::make('payment_status')
                                    ->label('Status płatności')
                                    ->options(EventSettlementCost::$paymentStatuses)
                                    ->required(),

                                Forms\Components\Select::make('advance_type')
                                    ->label('Typ płatności')
                                    ->options(EventSettlementCost::$advanceTypes)
                                    ->required(),

                                Forms\Components\TextInput::make('advance_amount')
                                    ->label('Kwota zaliczki / rezerwacji')
                                    ->numeric()
                                    ->suffix('PLN')
                                    ->nullable(),

                                Forms\Components\DateTimePicker::make('advance_due_date')
                                    ->label('Termin zaliczki / rezerwacji')
                                    ->nullable(),

                                Forms\Components\TextInput::make('actual_amount')
                                    ->label('Kwota rzeczywista')
                                    ->helperText('Kwota w walucie pozycji kosztowej.')
                                    ->numeric()
                                    ->nullable(),

                                Forms\Components\Select::make('payment_method')
                                    ->label('Forma płatności')
                                    ->options(EventSettlementCost::$paymentMethods)
                                    ->nullable(),

                                Forms\Components\TextInput::make('document_number')
                                    ->label('Numer dokumentu')
                                    ->maxLength(255)
                                    ->nullable(),

                                Forms\Components\DateTimePicker::make('paid_at')
                                    ->label('Data płatności')
                                    ->nullable(),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Uwagi do rozliczenia')
                                    ->rows(4)
                                    ->columnSpanFull()
                                    ->nullable(),
                            ]),
                    ])
                    ->action(function (array $data, EventProgramPoint $record): void {
                        $event = $this->getOwnerRecord();
                        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
                        $cost = $settlement->upsertCostFromProgramPoint($record->loadMissing('templatePoint', 'currency'));

                        $cost->fill([
                            'payment_status' => $data['payment_status'] ?? $cost->payment_status,
                            'advance_type' => $data['advance_type'] ?? $cost->advance_type,
                            'advance_amount' => $data['advance_amount'] ?? null,
                            'advance_due_date' => $data['advance_due_date'] ?? null,
                            'actual_amount' => $data['actual_amount'] ?? null,
                            'actual_currency_id' => $cost->planned_currency_id,
                            'actual_rate' => $cost->planned_rate,
                            'payment_method' => $data['payment_method'] ?? null,
                            'document_number' => $data['document_number'] ?? null,
                            'paid_at' => $data['paid_at'] ?? null,
                            'notes' => $data['notes'] ?? null,
                        ]);
                        $cost->save();

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Punkt rozliczony')
                            ->body('Pozycja kosztowa została zapisana w aktywnym rozliczeniu.')
                            ->send();
                    })
                    ->visible(fn (EventProgramPoint $record) => (bool) $record->active),

                Tables\Actions\Action::make('add_reservation')
                    ->label('Dodaj rezerwację')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->button()
                    ->extraAttributes(['class' => 'w-full'])
                    ->modalHeading(fn (EventProgramPoint $record) => 'Nowa rezerwacja dla: '.($record->name ?? $record->templatePoint?->name ?? 'Punkt #'.$record->id))
                    ->modalWidth('2xl')
                    ->form([
                        Forms\Components\TextInput::make('booking_reference')
                            ->label('Numer rezerwacji')
                            ->maxLength(255)
                            ->nullable(),

                        Forms\Components\Select::make('contractor_id')
                            ->label('Kontrahent')
                            ->options(fn () => \App\Models\Contractor::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->default(fn (EventProgramPoint $record) => $record->contractor_id)
                            ->nullable(),

                        Forms\Components\TextInput::make('participant_count')
                            ->label('Liczba uczestników')
                            ->numeric()
                            ->default(1)
                            ->required(),

                        Forms\Components\TextInput::make('reserved_amount')
                            ->label('Kwota rezerwacji (PLN)')
                            ->numeric()
                            ->suffix('PLN')
                            ->default(fn (EventProgramPoint $record) => $record->total_price)
                            ->helperText('Domyślnie pobierana z ceny całkowitej punktu programu.')
                            ->nullable(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(\App\Models\Reservation::$statuses)
                            ->default('pending')
                            ->required(),

                        Forms\Components\DateTimePicker::make('reserved_at')
                            ->label('Data rezerwacji')
                            ->default(now())
                            ->required(),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Wygasa')
                            ->nullable(),

                        Forms\Components\RichEditor::make('notes')
                            ->columnSpanFull()
                            ->nullable(),
                    ])
                    ->action(function (array $data, EventProgramPoint $record) {
                        \App\Models\Reservation::create([
                            ...$data,
                            'program_point_id' => $record->id,
                            'event_id' => $record->event_id,
                            'created_by' => auth()->id(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Rezerwacja dodana')
                            ->body('Utworzono rezerwację dla punktu programu: '.($record->name ?? $record->templatePoint?->name ?? 'Punkt #'.$record->id))
                            ->send();
                    }),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('duplicate')
                        ->label('Duplikuj')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('success')
                        ->action(fn (EventProgramPoint $record) => $record->duplicate()),

                    Tables\Actions\Action::make('move_to_day')
                        ->label('Przenieś do dnia')
                        ->icon('heroicon-o-arrow-right')
                        ->form([
                            Forms\Components\Select::make('new_day')
                                ->label('Dzień docelowy')
                                ->options(function () {
                                    $maxDay = (int) ($this->getOwnerRecord()->duration_days ?? 1);
                                    $options = [];
                                    for ($i = 1; $i <= $maxDay; $i++) {
                                        $options[$i] = "Dzień {$i}";
                                    }

                                    return $options;
                                })
                                ->required(),
                        ])
                        ->action(function (EventProgramPoint $record, array $data) {
                            $record->moveToDay((int) $data['new_day']);
                        }),

                    Tables\Actions\EditAction::make('edit')
                        ->extraModalFooterActions([
                            Tables\Actions\Action::make('add_reservation_from_modal')
                                ->label('Dodaj rezerwację')
                                ->icon('heroicon-o-plus-circle')
                                ->color('success')
                                ->modalHeading(fn (EventProgramPoint $record) => 'Nowa rezerwacja dla: '.($record->name ?? $record->templatePoint?->name ?? 'Punkt #'.$record->id))
                                ->modalWidth('2xl')
                                ->form([
                                    Forms\Components\TextInput::make('booking_reference')
                                        ->label('Numer rezerwacji')
                                        ->maxLength(255)
                                        ->nullable(),

                                    Forms\Components\Select::make('contractor_id')
                                        ->label('Kontrahent')
                                        ->options(fn () => \App\Models\Contractor::orderBy('name')->pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->default(fn (EventProgramPoint $record) => $record->contractor_id)
                                        ->nullable(),

                                    Forms\Components\TextInput::make('participant_count')
                                        ->label('Liczba uczestników')
                                        ->numeric()
                                        ->default(1)
                                        ->required(),

                                    Forms\Components\TextInput::make('reserved_amount')
                                        ->label('Kwota rezerwacji (PLN)')
                                        ->numeric()
                                        ->suffix('PLN')
                                        ->default(fn (EventProgramPoint $record) => $record->total_price)
                                        ->helperText('Domyślnie pobierana z ceny całkowitej punktu programu.')
                                        ->nullable(),

                                    Forms\Components\Select::make('status')
                                        ->label('Status')
                                        ->options(\App\Models\Reservation::$statuses)
                                        ->default('pending')
                                        ->required(),

                                    Forms\Components\DateTimePicker::make('reserved_at')
                                        ->label('Data rezerwacji')
                                        ->default(now())
                                        ->required(),

                                    Forms\Components\DateTimePicker::make('expires_at')
                                        ->label('Wygasa')
                                        ->nullable(),

                                    Forms\Components\RichEditor::make('notes')
                                        ->columnSpanFull()
                                        ->nullable(),
                                ])
                                ->action(function (array $data, EventProgramPoint $record) {
                                    \App\Models\Reservation::create([
                                        ...$data,
                                        'program_point_id' => $record->id,
                                        'event_id' => $record->event_id,
                                        'created_by' => auth()->id(),
                                    ]);

                                    \Filament\Notifications\Notification::make()
                                        ->success()
                                        ->title('Rezerwacja dodana')
                                        ->body('Utworzono rezerwację dla punktu programu: '.($record->name ?? $record->templatePoint?->name ?? 'Punkt #'.$record->id))
                                        ->send();
                                }),
                        ]),

                    Tables\Actions\DeleteAction::make(),
                ])
                    ->label('Więcej')
                    ->icon('heroicon-o-ellipsis-vertical'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('bulk_include_in_program_on')
                        ->label('Zaznacz w programie')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['include_in_program' => true])),

                    Tables\Actions\BulkAction::make('bulk_include_in_program_off')
                        ->label('Odznacz z programu')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->action(fn ($records) => $records->each->update(['include_in_program' => false])),

                    Tables\Actions\BulkAction::make('bulk_include_in_calculation_on')
                        ->label('Zaznacz w kalkulacji')
                        ->icon('heroicon-o-calculator')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['include_in_calculation' => true])),

                    Tables\Actions\BulkAction::make('bulk_include_in_calculation_off')
                        ->label('Odznacz z kalkulacji')
                        ->icon('heroicon-o-calculator')
                        ->color('gray')
                        ->action(fn ($records) => $records->each->update(['include_in_calculation' => false])),

                    Tables\Actions\BulkAction::make('bulk_program_only')
                        ->label('Zostaw tylko w programie')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $records->each->update([
                                'include_in_program' => true,
                                'include_in_calculation' => false,
                                'active' => true,
                            ]);
                        }),

                    Tables\Actions\BulkAction::make('bulk_include_in_settlement')
                        ->label('Przywróć do rozliczenia')
                        ->icon('heroicon-o-calculator')
                        ->color('success')
                        ->action(function ($records): void {
                            $records->each->update([
                                'include_in_program' => true,
                                'include_in_calculation' => true,
                                'active' => true,
                            ]);
                        }),

                    Tables\Actions\BulkAction::make('bulk_fix_legacy_prices')
                        ->label('Napraw kwoty legacy')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $updated = 0;

                            foreach ($records as $record) {
                                if ($this->normalizeLegacyPointPricing($record)) {
                                    $updated++;
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Naprawiono punkty legacy')
                                ->body('Zaktualizowano: '.$updated)
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('bulk_activate')
                        ->label('Aktywuj')
                        ->icon('heroicon-o-bolt')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['active' => true])),

                    Tables\Actions\BulkAction::make('bulk_deactivate')
                        ->label('Dezaktywuj')
                        ->icon('heroicon-o-bolt-slash')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['active' => false])),
                ]),
            ])
            ->emptyStateHeading('Brak punktów programu')
            ->emptyStateDescription('Dodaj punkty programu lub skopiuj je z szablonu.')
            ->emptyStateIcon('heroicon-o-calendar-days');
    }

    protected function resolveQuantityForProgramPoint(int $requestedQuantity, ?int $groupSize): int
    {
        $requestedQuantity = max(1, $requestedQuantity);
        $groupSize = (int) ($groupSize ?? 0);

        if ($groupSize <= 0 || $requestedQuantity > 1) {
            return $requestedQuantity;
        }

        $participants = max(1, (int) ($this->getOwnerRecord()->participant_count ?? 1));

        return max(1, (int) ceil($participants / $groupSize));
    }

    protected function updateProgramPointVisibility(
        EventProgramPoint $record,
        bool $includeInProgram,
        bool $includeInCalculation,
        bool $active,
        string $title
    ): void {
        $record->update([
            'include_in_program' => $includeInProgram,
            'include_in_calculation' => $includeInCalculation,
            'active' => $active,
        ]);

        \Filament\Notifications\Notification::make()
            ->success()
            ->title($title)
            ->body($record->name ?? $record->templatePoint?->name ?? ('Punkt #'.$record->id))
            ->send();
    }

    protected function isLegacySingleUnitPoint(EventProgramPoint $record): bool
    {
        if (blank($record->event_template_program_point_id)) {
            return false;
        }

        $unit = (float) ($record->unit_price ?? 0);
        $storedTotal = (float) ($record->total_price ?? 0);
        $storedQuantity = max(1, (int) ($record->quantity ?? 1));
        $participantCount = (int) ($this->getOwnerRecord()->participant_count ?? 1);
        $expectedQuantity = $record->resolveCalculatedQuantity($participantCount);

        return $storedQuantity <= 1
            && abs($storedTotal - $unit) < 0.01
            && $expectedQuantity > 1
            && $unit > 0;
    }

    protected function normalizeLegacyPointPricing(EventProgramPoint $record): bool
    {
        if (! $this->isLegacySingleUnitPoint($record)) {
            return false;
        }

        $participantCount = (int) ($this->getOwnerRecord()->participant_count ?? 1);
        $quantity = $record->resolveCalculatedQuantity($participantCount);
        $total = $record->resolveEffectiveTotalPrice($participantCount);

        $record->update([
            'quantity' => $quantity,
            'total_price' => $total,
        ]);

        return true;
    }

    public function reorderTable(array $order): void
    {
        try {
            \Illuminate\Support\Facades\Log::info('Początek reorderTable Event z grupowaniem', ['order' => $order]);

            \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
                $affectedDays = [];
                $objectPayload = ! empty($order) && is_array($order) && isset($order[0]) && is_array($order[0]) && isset($order[0]['id']);

                if ($objectPayload) {
                    foreach ($order as $item) {
                        $recordId = (int) ($item['id'] ?? 0);
                        if ($recordId <= 0) {
                            continue;
                        }

                        $record = EventProgramPoint::query()
                            ->where('event_id', $this->getOwnerRecord()->id)
                            ->find($recordId);

                        if (! $record) {
                            continue;
                        }

                        $oldDay = (int) $record->day;
                        $newDay = (int) ($item['day'] ?? $record->day);
                        $newOrder = max(1, (int) ($item['order'] ?? $record->order));
                        $rawParent = array_key_exists('parent_id', $item) ? $item['parent_id'] : $record->parent_id;
                        $newParent = blank($rawParent) || (int) $rawParent <= 0 ? null : (int) $rawParent;

                        if ($newParent === $record->id) {
                            $newParent = null;
                        }

                        $record->update([
                            'day' => $newDay,
                            'order' => $newOrder,
                            'parent_id' => $newParent,
                        ]);

                        $affectedDays[$oldDay] = true;
                        $affectedDays[$newDay] = true;
                    }
                } else {
                    foreach ($order as $index => $recordId) {
                        $record = EventProgramPoint::query()
                            ->where('event_id', $this->getOwnerRecord()->id)
                            ->find((int) $recordId);

                        if (! $record) {
                            continue;
                        }

                        $record->update([
                            'order' => $index + 1,
                        ]);

                        $affectedDays[(int) $record->day] = true;
                    }
                }

                foreach (array_keys($affectedDays) as $day) {
                    $this->normalizeOrderForDay((int) $day);
                }
            });

            \Illuminate\Support\Facades\Log::info('Koniec reorderTable Event - sukces');

            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Zapisano kolejność')
                ->body('Nowa kolejność punktów programu została zapisana.')
                ->send();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Błąd podczas przestawiania Event: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Nie udało się zapisać kolejności')
                ->body('Spróbuj ponownie. Jeśli problem się powtarza, odśwież stronę.')
                ->send();
        }
    }

    protected function normalizeOrderForDay(int $day): void
    {
        if ($day < 1) {
            return;
        }

        $points = EventProgramPoint::query()
            ->where('event_id', $this->getOwnerRecord()->id)
            ->where('day', $day)
            ->orderBy('order')
            ->orderBy('id')
            ->get(['id', 'order']);

        foreach ($points as $index => $point) {
            $expectedOrder = $index + 1;
            if ((int) $point->order === $expectedOrder) {
                continue;
            }

            EventProgramPoint::where('id', $point->id)->update([
                'order' => $expectedOrder,
            ]);
        }
    }

    protected function getProgramPointEditorToolbarButtons(): array
    {
        return [
            'h2',
            'h3',
            'bold',
            'italic',
            'underline',
            'strike',
            'blockquote',
            'bulletList',
            'orderedList',
            'link',
            'undo',
            'redo',
        ];
    }

    protected function getTableQueryForPage(): Builder
    {
        $query = parent::getTableQueryForPage()
            ->with([
                'contractor',
                'templatePoint',
                'reservations.contractor',
            ]);

        // Dodajemy puste rekordy dla dni bez punktów programu
        $event = $this->getOwnerRecord();
        $maxDay = (int) ($event->duration_days ?? 1);

        // Sprawdzamy które dni mają punkty programu
        $usedDays = $event->programPoints()->distinct('day')->pluck('day')->toArray();

        // Dla dni bez punktów, tworzymy "phantom" rekordy (nie zapisujemy do bazy)
        // To jest tylko do wyświetlenia pustych grup
        for ($day = 1; $day <= $maxDay; $day++) {
            if (! in_array($day, $usedDays)) {
                // Dla pustych dni Filament automatycznie pokaże pustą grupę
                // gdy użyjemy defaultGroup
            }
        }

        return $query;
    }
}
