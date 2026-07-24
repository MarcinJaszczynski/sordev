<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Forms\EventKeyInfoFields;
use App\Filament\Forms\EventNotesFields;
use App\Filament\Forms\EventOrderingPartyFields;
use App\Filament\Forms\EventTransportFields;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Traits\SearchContractorTrait;
use App\Models\Bus;
use App\Models\Contractor;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Place;
use App\Models\PlaceDistance;
use App\Models\User;
use App\Services\EventInquiryNotificationService;
use Filament\Forms;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;

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
                    ->operation('create')
                    ->model($this->getModel())
                    ->schema($this->getFormSchema())
                    ->statePath('data')
            ),
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Zamawiający')
                ->icon('heroicon-o-user-circle')
                ->description('Wymagane: wyszukaj osobę w bazie albo — gdy jej nie ma — wprowadź ręcznie z telefonem lub e-mailem.')
                ->schema([
                    Forms\Components\View::make('filament.components.event-client-lookup-wrapper')
                        ->columnSpanFull(),
                    Forms\Components\Hidden::make('client_name')
                        ->dehydrated(),
                    Forms\Components\Hidden::make('client_email')
                        ->dehydrated(),
                    Forms\Components\Hidden::make('client_phone')
                        ->dehydrated(),
                    EventOrderingPartyFields::orderingPartiesRepeater()
                        ->label('Zaawansowane / ręczna korekta')
                        ->minItems(0)
                        ->defaultItems(0)
                        ->collapsible()
                        ->collapsed()
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Uwagi klienta/uwagi dla biura')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->schema([
                    EventNotesFields::generalNotes()
                        ->hiddenLabel()
                        ->columnSpanFull(),
                    EventNotesFields::officeNotes()
                        ->hiddenLabel()
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Szablon i parametry')
                ->icon('heroicon-o-rectangle-stack')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('event_template_id')
                        ->label('Szablon imprezy')
                        ->options(EventTemplate::where('deleted_at', null)->pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('Bez szablonu (impreza czysta)')
                        ->nullable()
                        ->reactive()
                        ->columnSpanFull()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $this->template = $state ? EventTemplate::find($state) : null;

                            if ($this->template) {
                                $set('duration_days', $this->template->duration_days);
                                $set('program_km', $this->template->program_km);
                                $set('bus_id', $this->template->bus_id);
                                $set('markup_id', $this->template->markup_id);
                                $set('name', $this->template->name);

                                $startPlaceId = (int) ($get('start_place_id') ?? 0);
                                $allowed = $this->template->resolveAvailableStartPlaceIds();
                                if ($startPlaceId > 0 && $allowed->isNotEmpty() && ! $allowed->contains($startPlaceId)) {
                                    $set('start_place_id', null);
                                    $startPlaceId = 0;
                                }

                                $set('transfer_km', EventResource::resolveTransferKmFromTemplateState(
                                    (int) $this->template->id,
                                    $startPlaceId,
                                    (float) ($this->template->transfer_km ?? 0)
                                ));
                            }

                            $this->refreshTotalCostFromTemplateState($set, $get);
                        })
                        ->helperText('Wybierz szablon, na podstawie którego zostanie utworzona impreza'),

                    ...EventKeyInfoFields::identitySection(),

                    Forms\Components\TextInput::make('participant_count')
                        ->label('Liczba uczestników')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (callable $get, callable $set) => $this->refreshTotalCostFromTemplateState($set, $get))
                        ->required(),

                    Forms\Components\TextInput::make('gratis_count')
                        ->label(\App\Support\EventParticipantGroupLabels::GRATIS)
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->dehydrated()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (callable $get, callable $set) => $this->refreshTotalCostFromTemplateState($set, $get))
                        ->helperText('Osoby jadące w grupie bez opłaty za siebie. Uwzględniane w kalkulacji kosztów i zapisywane w wariancie ilościowym grupy.'),

                    Forms\Components\Select::make('start_place_id')
                        ->label('Miejsce wyjazdu (podstawienia)')
                        ->options(fn (callable $get) => Place::startingPlaceSelectOptionsForTemplate(
                            (int) ($get('event_template_id') ?? 0) ?: null,
                            (int) ($get('start_place_id') ?? 0) ?: null,
                        ))
                        ->searchable()
                        ->nullable()
                        ->reactive()
                        ->helperText(fn (callable $get): string => filled($get('event_template_id'))
                            ? 'Punkty startowe dostępne dla wybranego szablonu.'
                            : 'Tylko miejsca oznaczone jako punkty startowe — wymagane do kalkulacji transferu.')
                        ->afterStateUpdated(function (callable $get, callable $set): void {
                            $templateId = (int) ($get('event_template_id') ?? 0);
                            $startPlaceId = (int) ($get('start_place_id') ?? 0);
                            $currentTransfer = (float) ($get('transfer_km') ?? 0);

                            if ($templateId) {
                                $set('transfer_km', EventResource::resolveTransferKmFromTemplateState(
                                    $templateId,
                                    $startPlaceId,
                                    $currentTransfer
                                ));
                            } else {
                                $programStartPlaceId = (int) ($get('program_start_place_id') ?? 0);
                                if ($programStartPlaceId > 0 && $startPlaceId > 0) {
                                    $d1 = (float) (PlaceDistance::query()
                                        ->where('from_place_id', $startPlaceId)
                                        ->where('to_place_id', $programStartPlaceId)
                                        ->value('distance_km') ?? 0);
                                    $set('transfer_km', $d1 * 2);
                                }
                            }

                            $this->refreshTotalCostFromTemplateState($set, $get);
                        }),

                    Forms\Components\Select::make('program_start_place_id')
                        ->label('Początek programu')
                        ->options(Place::pluck('name', 'id'))
                        ->searchable()
                        ->nullable()
                        ->dehydrated(false)
                        ->reactive()
                        ->visible(fn (callable $get) => empty($get('event_template_id')))
                        ->afterStateUpdated(function (callable $get, callable $set): void {
                            $startPlaceId = (int) ($get('start_place_id') ?? 0);
                            $programStartPlaceId = (int) ($get('program_start_place_id') ?? 0);
                            if ($programStartPlaceId > 0 && $startPlaceId > 0) {
                                $d1 = (float) (PlaceDistance::query()
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
                        ->afterStateUpdated(fn ($livewire, callable $get, callable $set) => [
                            $this->refreshTotalCostFromTemplateState($set, $get),
                            method_exists($livewire, 'dispatch') ? $livewire->dispatch('event-price-table-refresh') : null,
                        ]),

                    Forms\Components\Select::make('bus_id')
                        ->label('Autokar')
                        ->options(Bus::pluck('name', 'id'))
                        ->searchable()
                        ->nullable()
                        ->live()
                        ->afterStateUpdated(fn ($livewire) => $livewire->dispatch('event-price-table-refresh')),
                ]),

            Forms\Components\Section::make('Przewoźnik i kierowca')
                ->icon('heroicon-o-truck')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->schema([
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

                    \App\Filament\Forms\PhoneInput::make('driver_phone')
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

                    Forms\Components\Select::make('contractor_id')
                        ->label('Wykonawca (kontrahent)')
                        ->options(Contractor::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),

                    ...EventTransportFields::manualTransportCostFields(),

                    EventNotesFields::driverNotes()
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Zakwaterowanie')
                ->icon('heroicon-o-building-office-2')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Placeholder::make('hotel_info')
                        ->hiddenLabel()
                        ->content('Szczegóły hoteli (i ich uwagi) uzupełnisz w zakładce „Hotele” po utworzeniu imprezy.'),
                ]),

            Forms\Components\Section::make('Kalkulacja ceny')
                ->icon('heroicon-o-calculator')
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
                        ->hidden(fn (callable $get) => ! $get('event_template_id') || ! $get('start_place_id')),

                    Forms\Components\TextInput::make('total_cost')
                        ->label('Całkowity koszt (PLN)')
                        ->numeric()
                        ->prefix('PLN')
                        ->default(0)
                        ->readOnly()
                        ->helperText('Koszt jest obliczany na podstawie wyboru parametrów'),

                    Forms\Components\Hidden::make('markup_id'),
                ]),

            Forms\Components\Section::make('Zapis')
                ->icon('heroicon-o-check-circle')
                ->schema([
                    Forms\Components\Hidden::make('status')
                        ->default(Event::STATUS_INQUIRY)
                        ->dehydrated(),

                    Forms\Components\Checkbox::make('notify_office_about_inquiry')
                        ->label('Powiadom biuro o nowym zapytaniu')
                        ->helperText('Utworzy zadanie dla ról admin, super_admin i biuro z linkiem do imprezy.')
                        ->default(false)
                        ->dehydrated(false),
                ]),
        ];
    }

    protected function beforeValidate(): void
    {
        $this->syncClientFieldsFromOrderingParties();

        $errors = app(\App\Services\EventOrderingPartyService::class)->validateForEventCreation(
            is_array($this->data['ordering_parties'] ?? null) ? $this->data['ordering_parties'] : null,
            $this->data['client_name'] ?? null,
            $this->data['client_phone'] ?? null,
            $this->data['client_email'] ?? null,
        );

        if ($errors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages(
                collect($errors)->mapWithKeys(
                    fn (string $message, string $key): array => ['data.'.$key => [$message]]
                )->all()
            );
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->syncClientFieldsFromOrderingParties();

        if (blank($data['client_name'] ?? null)) {
            $parties = $data['ordering_parties'] ?? $this->data['ordering_parties'] ?? [];
            if (is_array($parties) && $parties !== []) {
                $attrs = app(\App\Services\EventOrderingPartyService::class)->primaryClientAttributes($parties);
                $data['client_name'] = $attrs['client_name'] ?? $data['client_name'] ?? null;
                $data['client_email'] = $attrs['client_email'] ?? $data['client_email'] ?? null;
                $data['client_phone'] = $attrs['client_phone'] ?? $data['client_phone'] ?? null;
            }
        }

        return $data;
    }

    protected function syncClientFieldsFromOrderingParties(): void
    {
        if (filled($this->data['client_name'] ?? null)) {
            return;
        }

        $parties = $this->data['ordering_parties'] ?? [];

        if (! is_array($parties) || $parties === []) {
            return;
        }

        $attrs = app(\App\Services\EventOrderingPartyService::class)->primaryClientAttributes($parties);

        if (blank($attrs['client_name'] ?? null)) {
            return;
        }

        $this->data['client_name'] = $attrs['client_name'];
        $this->data['client_email'] = $attrs['client_email'] ?? $this->data['client_email'] ?? null;
        $this->data['client_phone'] = $attrs['client_phone'] ?? $this->data['client_phone'] ?? null;
    }

    public function create(bool $another = false): void
    {
        try {
            parent::create($another);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            \Filament\Notifications\Notification::make()
                ->title('Nie można utworzyć imprezy')
                ->body(collect($exception->errors())->flatten()->unique()->implode("\n"))
                ->danger()
                ->send();

            throw $exception;
        }
    }

    protected function afterCreate(): void
    {
        if ($this->record instanceof Event) {
            $this->record->syncPrimaryClientFromOrderingParties();

            if ((bool) ($this->data['notify_office_about_inquiry'] ?? false)) {
                app(EventInquiryNotificationService::class)->notifyOfficeAboutNewInquiry(
                    $this->record->fresh(),
                    Auth::user(),
                );
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $orderingParties
     */
    #[On('client-lookup-applied')]
    public function applyClientLookup(
        array $orderingParties,
        string $clientName = '',
        ?string $clientEmail = null,
        ?string $clientPhone = null,
    ): void {
        $this->data['ordering_parties'] = $orderingParties;
        $this->data['client_name'] = $clientName;
        $this->data['client_email'] = $clientEmail;
        $this->data['client_phone'] = $clientPhone;
    }

    #[On('client-lookup-cleared')]
    public function clearClientLookup(): void
    {
        $this->data['ordering_parties'] = [];
        $this->data['client_name'] = null;
        $this->data['client_email'] = null;
        $this->data['client_phone'] = null;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $pilotBirth = $data['pilot_birth_date'] ?? null;
        $pilotPesel = $data['pilot_pesel'] ?? null;
        $pilotPhone = $data['pilot_phone'] ?? null;
        $orderingParties = $data['ordering_parties'] ?? null;
        $orderingContractorIds = $data['orderingContractors'] ?? null;
        unset($data['ordering_parties'], $data['orderingContractors'], $data['pilot_birth_date'], $data['pilot_pesel'], $data['pilot_phone']);

        if (! empty($data['start_date']) && empty($data['end_date'])) {
            $start = \Carbon\Carbon::parse($data['start_date']);
            $durationDays = max(1, (int) ($data['duration_days'] ?? 1));
            $data['end_date'] = $start->copy()->addDays($durationDays - 1)->toDateString();
        }

        if (! Schema::hasColumn('events', 'contractor_id')) {
            unset($data['contractor_id']);
        }

        $template = $this->template ?? EventTemplate::find($data['event_template_id'] ?? null);

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

            $event = Event::createFromTemplate($template, array_merge($data, [
                'ordering_parties' => $orderingParties,
                'orderingContractors' => $orderingContractorIds,
            ]));
            Log::info('CreateEvent:web:after_create_from_template', ['event_id' => $event->id]);

            User::syncPilotDemographics($event->assigned_to, $pilotBirth, $pilotPesel, $pilotPhone);

            return $event;
        }

        unset($data['orderingContractors'], $data['ordering_parties']);

        $event = parent::handleRecordCreation($data);

        if ($event instanceof Event && is_array($orderingParties) && $orderingParties !== []) {
            $event->syncOrderingParties($orderingParties);
        } elseif ($event instanceof Event && is_array($orderingContractorIds) && $orderingContractorIds !== []) {
            $event->syncOrderingContractors($orderingContractorIds);
        }

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

        User::syncPilotDemographics($event->assigned_to, $pilotBirth, $pilotPesel, $pilotPhone);

        return $event;
    }

    protected function refreshTotalCostFromTemplateState(callable $set, callable $get): void
    {
        $templateId = $get('event_template_id');
        $startPlaceId = $get('start_place_id');
        $participantCount = (int) ($get('participant_count') ?? 1);
        $gratisCount = max(0, (int) ($get('gratis_count') ?? 0));

        if (! $templateId || ! $startPlaceId || $participantCount < 1) {
            $set('total_cost', 0);

            return;
        }

        $set('total_cost', $this->resolveTotalCostFromTemplate((int) $templateId, (int) $startPlaceId, $participantCount, $gratisCount));
    }

    protected function resolveTotalCostFromTemplate(int $templateId, int $startPlaceId, int $participantCount, int $gratisCount): float
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
        if (! empty($plnIds)) {
            $plnRows = $priceRows->whereIn('currency_id', $plnIds);
            if ($plnRows->isNotEmpty()) {
                $priceRows = $plnRows;
            }
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
