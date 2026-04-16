<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Traits\SearchContractorTrait;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Bus;
use App\Models\Contractor;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Place;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Filament\Forms;

class CreateEvent extends CreateRecord
{
    use SearchContractorTrait;

    protected static string $resource = EventResource::class;

    protected ?EventTemplate $template = null;

    public function mount(): void
    {
        parent::mount();

        $templateId = request()->query('template');
        if ($templateId) {
            $this->template = EventTemplate::find($templateId);
            if ($this->template) {
                // Prefill some form data from template
                $this->form->fill([
                    'event_template_id' => $this->template->id,
                    'duration_days' => $this->template->duration_days,
                    'transfer_km' => $this->template->transfer_km,
                    'program_km' => $this->template->program_km,
                    'bus_id' => $this->template->bus_id,
                    'markup_id' => $this->template->markup_id,
                    'name' => $this->template->name,
                ]);
            }
        }
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema($this->getFormSchema())
                    ->statePath('data')
            ),
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Wybór szablonu')
                ->icon('heroicon-o-rectangle-stack')
                ->schema([
                    Forms\Components\Select::make('event_template_id')
                        ->label('Szablon imprezy')
                            ->options(EventTemplate::where('deleted_at', null)->pluck('name', 'id'))
                            ->searchable()
                        ->placeholder('Bez szablonu (impreza czysta)')
                        ->nullable()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $this->template = $state ? EventTemplate::find($state) : null;

                            if ($this->template) {
                                $set('duration_days', $this->template->duration_days);
                                $set('program_km', $this->template->program_km);
                                $set('bus_id', $this->template->bus_id);
                                $set('markup_id', $this->template->markup_id);
                                $set('name', $this->template->name);

                                $set('transfer_km', EventResource::resolveTransferKmFromTemplateState(
                                    (int) $this->template->id,
                                    (int) ($get('start_place_id') ?? 0),
                                    (float) ($this->template->transfer_km ?? 0)
                                ));
                            }

                            $this->refreshTotalCostFromTemplateState($set, $get);
                        })
                        ->helperText('Wybierz szablon, na podstawie którego zostanie utworzona impreza'),
                ]),

            Forms\Components\Section::make('Zamawiający (klient)')
                ->icon('heroicon-o-magnifying-glass')
                ->schema([
                    Forms\Components\Select::make('customer_lookup_id')
                        ->label('Wyszukaj zamawiającego w bazie')
                        ->searchable()
                        ->preload()
                        ->dehydrated(false)
                        ->options(fn (): array => self::getContractorOptions()->toArray())
                        ->getSearchResultsUsing(fn (string $search): array => self::getContractorOptions($search)->toArray())
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
                        ->createOptionForm([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nazwa klienta / firmy')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Telefon')
                                    ->tel()
                                    ->maxLength(50),
                                Forms\Components\TextInput::make('email')
                                    ->label('E-mail')
                                    ->email()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('nip')
                                    ->label('NIP')
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('city')
                                    ->label('Miasto')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('street')
                                    ->label('Ulica')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('house_number')
                                    ->label('Nr domu')
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('postal_code')
                                    ->label('Kod pocztowy')
                                    ->maxLength(20),
                                Forms\Components\RichEditor::make('office_notes')
                                    ->columnSpanFull(),
                            ]),
                        ])
                        ->createOptionUsing(function (array $data): int {
                            $contractor = Contractor::create([
                                'name'         => $data['name'],
                                'phone'        => $data['phone'] ?? null,
                                'email'        => $data['email'] ?? null,
                                'nip'          => $data['nip'] ?? null,
                                'street'       => $data['street'] ?? null,
                                'house_number' => $data['house_number'] ?? null,
                                'city'         => $data['city'] ?? null,
                                'postal_code'  => $data['postal_code'] ?? null,
                                'office_notes' => $data['office_notes'] ?? null,
                                'status'       => 'active',
                            ]);

                            return $contractor->id;
                        })
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $contractor = Contractor::find($state);
                                if ($contractor) {
                                    $contractorData = self::mapContractorToEventData($contractor);
                                    $set('client_name', $contractorData['client_name']);
                                    $set('client_email', $contractorData['client_email']);
                                    $set('client_phone', $contractorData['client_phone']);
                                }
                            }
                        })
                        ->helperText('Możesz wybrać istniejącego zamawiającego albo dodać nowego z poziomu formularza.')
                        ->nullable(),

                    Forms\Components\TextInput::make('client_name')
                        ->label('Zamawiający')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('client_email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('client_phone')
                        ->label('Telefon')
                        ->tel()
                        ->maxLength(20),
                ]),

            Forms\Components\Section::make('Dane imprezy')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nazwa imprezy')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Nazwa dla rozpoznania imprezy w systemie'),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(Event::getStatusOptions())
                        ->default(Event::STATUS_INQUIRY)
                        ->required(),

                    Forms\Components\Group::make()
                        ->columns(3)
                        ->schema([
                            Forms\Components\DatePicker::make('start_date')
                                ->label('Data rozpoczęcia')
                                ->required()
                                ->native(false),

                            Forms\Components\DatePicker::make('end_date')
                                ->label('Data zakończenia')
                                ->native(false),

                            Forms\Components\TextInput::make('duration_days')
                                ->label('Liczba dni')
                                ->numeric()
                                ->minValue(1)
                                ->default(1)
                                ->readOnly()
                                ->helperText('Obliczana na podstawie dat'),

                            Forms\Components\TextInput::make('participant_count')
                                ->label('Liczba uczestników')
                                ->numeric()
                                ->minValue(1)
                                ->default(1)
                                ->reactive()
                                ->afterStateUpdated(fn (callable $get, callable $set) => $this->refreshTotalCostFromTemplateState($set, $get))
                                ->required(),

                            Forms\Components\TextInput::make('gratis_count')
                                ->label('Liczba gratisów')
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->reactive()
                                ->afterStateUpdated(fn (callable $get, callable $set) => $this->refreshTotalCostFromTemplateState($set, $get))
                                ->required(),
                        ]),
                ]),

            Forms\Components\Section::make('Transport i parametry techniczne')
                ->icon('heroicon-o-truck')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Group::make()
                        ->columns(2)
                        ->schema([
                            Forms\Components\TextInput::make('transfer_km')
                                ->label('Kilometry transferu')
                                ->numeric()
                                ->minValue(0)
                                ->default(0),

                            Forms\Components\TextInput::make('program_km')
                                ->label('Kilometry programu')
                                ->numeric()
                                ->minValue(0)
                                ->default(0),

                            Forms\Components\Select::make('start_place_id')
                                ->label('Miejsce podstawienia')
                                ->options(Place::pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function (callable $get, callable $set): void {
                                    $templateId = (int) ($get('event_template_id') ?? 0);
                                    $startPlaceId = (int) ($get('start_place_id') ?? 0);
                                    $currentTransfer = (float) ($get('transfer_km') ?? 0);

                                    $set('transfer_km', EventResource::resolveTransferKmFromTemplateState(
                                        $templateId,
                                        $startPlaceId,
                                        $currentTransfer
                                    ));

                                    $this->refreshTotalCostFromTemplateState($set, $get);
                                })
                                ->helperText('Wybierz miejsce wyjazdu dla zmiennego transferu'),

                            Forms\Components\TimePicker::make('departure_time')
                                ->label('Godzina podstawienia')
                                ->seconds(false)
                                ->native(false)
                                ->nullable()
                                ->visible(fn (): bool => Schema::hasColumn('events', 'departure_time')),

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
                                ->label('Szczegóły miejsca podstawienia')
                                ->columnSpanFull()
                                ->visible(fn (): bool => Schema::hasColumn('events', 'pickup_place_details'))
                                ->helperText('Np. dokładny adres, brama, punkt orientacyjny.'),

                            Forms\Components\Select::make('contractor_id')
                                ->label('Wykonawca (kontrahent)')
                                ->options(Contractor::orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->nullable(),

                            Forms\Components\Select::make('bus_id')
                                ->label('Autokar')
                                ->options(Bus::pluck('name', 'id'))
                                ->searchable()
                                ->nullable(),
                        ]),
                ]),

            Forms\Components\Section::make('Uwagi dla poszczególnych działów')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\RichEditor::make('office_notes')
                        ->label('Uwagi dla biura')
                        ->visible(fn (): bool => Schema::hasColumn('events', 'office_notes')),

                    Forms\Components\RichEditor::make('hotel_notes')
                        ->label('Uwagi dla hotelu')
                        ->visible(fn (): bool => Schema::hasColumn('events', 'hotel_notes')),

                    Forms\Components\RichEditor::make('pilot_notes')
                        ->label('Uwagi dla pilota')
                        ->visible(fn (): bool => Schema::hasColumn('events', 'pilot_notes')),

                    Forms\Components\RichEditor::make('driver_notes')
                        ->label('Uwagi dla kierowcy')
                        ->visible(fn (): bool => Schema::hasColumn('events', 'driver_notes')),

                    Forms\Components\RichEditor::make('notes')
                        ->placeholder('np. specjalne wymagania, alergie pokarmowe, preferowane hotele'),
                ]),

            Forms\Components\Section::make('Obliczenie ceny')
                ->icon('heroicon-o-calculator')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\View::make('components.event-price-calculation')
                        ->viewData(fn (callable $get) => [
                            'template' => EventTemplate::find($get('event_template_id')),
                            'participantCount' => $get('participant_count') ?? 1,
                            'gratisCount' => $get('gratis_count') ?? 0,
                            'startPlaceId' => $get('start_place_id'),
                            'calculatedTotal' => ($get('event_template_id') && $get('start_place_id') && ((int) ($get('participant_count') ?? 0) > 0))
                                ? $get('total_cost')
                                : null,
                        ])
                        ->hidden(fn (callable $get) => !$get('event_template_id') || !$get('start_place_id')),

                    Forms\Components\TextInput::make('total_cost')
                        ->label('Całkowity koszt (PLN)')
                        ->numeric()
                        ->prefix('PLN')
                        ->default(0)
                        ->readOnly()
                        ->helperText('Koszt jest obliczany na podstawie wybioru parametrów'),

                    Forms\Components\Hidden::make('markup_id'),
                ]),

            Forms\Components\Section::make('Przydzielenie i opcje')
                ->icon('heroicon-o-user-plus')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('assigned_to')
                        ->label('Przypisz do pracownika')
                        ->options(User::pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                ]),
        ];
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        if (! empty($data['start_date']) && empty($data['end_date'])) {
            $start = \Carbon\Carbon::parse($data['start_date']);
            $durationDays = max(1, (int) ($data['duration_days'] ?? 1));
            $data['end_date'] = $start->copy()->addDays($durationDays - 1)->toDateString();
        }

        if (!Schema::hasColumn('events', 'contractor_id')) {
            unset($data['contractor_id']);
        }

        $template = $this->template ?? EventTemplate::find($data['event_template_id'] ?? null);

        // If template present, use Event::createFromTemplate to ensure deep-copy behavior
        if ($template) {
            Log::info('CreateEvent:web:before_create_from_template', [
                'event_template_id' => $template->id,
                'name' => $data['name'] ?? null,
                'participant_count' => $data['participant_count'] ?? null,
                'gratis_count' => $data['gratis_count'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'duration_days' => $data['duration_days'] ?? null,
                'start_place_id' => $data['start_place_id'] ?? null,
            ]);

            $event = Event::createFromTemplate($template, $data);
            Log::info('CreateEvent:web:after_create_from_template', ['event_id' => $event->id]);
            return $event;
        }

        $event = parent::handleRecordCreation($data);

        if ($event instanceof Event && array_key_exists('gratis_count', $data)) {
            try {
                $event->syncQtyVariantForGroup(
                    (int) ($data['participant_count'] ?? 1),
                    (int) ($data['gratis_count'] ?? 0)
                );
            } catch (\Throwable $e) {
                Log::warning('CreateEvent:web:sync_qty_variant_failed_no_template', [
                    'event_id' => $event->id,
                    'participant_count' => $data['participant_count'] ?? null,
                    'gratis_count' => $data['gratis_count'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $event;
    }

    protected function refreshTotalCostFromTemplateState(callable $set, callable $get): void
    {
        $templateId = $get('event_template_id');
        $startPlaceId = $get('start_place_id');
        $participantCount = (int) ($get('participant_count') ?? 1);
        $gratisCount = max(0, (int) ($get('gratis_count') ?? 0));

        if (!$templateId || !$startPlaceId || $participantCount < 1) {
            $set('total_cost', 0);
            return;
        }

        $set('total_cost', $this->resolveTotalCostFromTemplate((int) $templateId, (int) $startPlaceId, $participantCount, $gratisCount));
    }

    protected function resolveTotalCostFromTemplate(int $templateId, int $startPlaceId, int $participantCount, int $gratisCount): float
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
                if (array_key_exists('price_with_tax', $exact) && $exact['price_with_tax'] !== null) {
                    return round((float) $exact['price_with_tax'], 2);
                }

                if (array_key_exists('price_per_person', $exact) && $exact['price_per_person'] !== null) {
                    return round(((float) $exact['price_per_person']) * $participantCount, 2);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('CreateEvent:custom_group_calculation_failed', [
                'event_template_id' => $templateId,
                'start_place_id' => $startPlaceId,
                'participant_count' => $participantCount,
                'gratis_count' => $gratisCount,
                'error' => $e->getMessage(),
            ]);
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

        $plnIds = Currency::plnIds();
        if (!empty($plnIds)) {
            $plnRows = $priceRows->whereIn('currency_id', $plnIds);
            if ($plnRows->isNotEmpty()) {
                $priceRows = $plnRows;
            }
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

        if ($bestMatch->price_with_tax !== null) {
            return round((float) $bestMatch->price_with_tax, 2);
        }

        $variantQty = (int) optional($bestMatch->eventTemplateQty)->qty;
        if ($variantQty > 0) {
            return round(((float) $bestMatch->price_per_person) * $variantQty, 2);
        }

        return round(((float) $bestMatch->price_per_person) * $participantCount, 2);
    }
}
