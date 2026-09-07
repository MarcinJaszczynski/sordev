<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Forms\EventKeyInfoFields;
use App\Filament\Forms\EventNotesFields;
use App\Filament\Forms\EventOrderingPartyFields;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\InteractsWithEventOrderingPartyLookups;
use App\Filament\Resources\EventResource\Traits\SearchContractorTrait;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\User;
use App\Services\EventInquiryNotificationService;
use App\Services\PilotContractorAssignmentService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Create = szybkie zapytanie (inquiry).
 * Transport, hotel, pilot, pełna kalkulacja — po zapisie na Podsumowaniu / w hubach.
 */
class CreateEvent extends CreateRecord
{
    use InteractsWithEventOrderingPartyLookups;
    use SearchContractorTrait;

    protected static string $resource = EventResource::class;

    protected ?EventTemplate $template = null;

    public function getSubheading(): ?string
    {
        return 'Tylko dane startowe zapytania — resztę uzupełnisz na karcie imprezy';
    }

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
                    'program_start_place_id' => $this->template->start_place_id,
                    // fill() nadpisuje cały stan — bez tego giną default(1) z EventKeyInfoFields
                    'participant_count' => 1,
                    'gratis_count' => 0,
                    'staff_count' => 1,
                    'driver_count' => 1,
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
            Forms\Components\Section::make('Nowe zapytanie')
                ->icon('heroicon-o-sparkles')
                ->description('Minimalny formularz startowy. Transport, hotel, pilota, dokumenty i pełną kalkulację uzupełnisz po utworzeniu — na Podsumowaniu oraz w hubach Operacje / Finanse.')
                ->schema([
                    Forms\Components\Placeholder::make('create_flow_hint')
                        ->hiddenLabel()
                        ->content('Status startowy: zapytanie. Po zapisie przejdziesz od razu do karty imprezy.'),
                ]),

            Forms\Components\Section::make('Zamawiający')
                ->icon('heroicon-o-user-circle')
                ->description('Wyszukaj w bazie albo „Dodaj nowego klienta” → „Zapisz klienta i wybierz go”. Dopiero potem zapisuj zapytanie.')
                ->schema(EventOrderingPartyFields::clientLookupFields()),

            Forms\Components\Section::make('Szablon')
                ->icon('heroicon-o-rectangle-stack')
                ->schema([
                    Forms\Components\Select::make('event_template_id')
                        ->label('Szablon imprezy')
                        ->options(EventTemplate::where('deleted_at', null)->pluck('name', 'id'))
                        ->searchable()
                        ->placeholder(fn (): string => $this->canCreateWithoutTemplate()
                            ? 'Bez szablonu (impreza czysta)'
                            : 'Wybierz szablon')
                        ->nullable(fn (): bool => $this->canCreateWithoutTemplate())
                        ->required(fn (): bool => ! $this->canCreateWithoutTemplate())
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $this->template = $state ? EventTemplate::find($state) : null;

                            if ($this->template) {
                                $set('duration_days', $this->template->duration_days);
                                $set('program_km', $this->template->program_km);
                                $set('bus_id', $this->template->bus_id);
                                $set('markup_id', $this->template->markup_id);
                                $set('name', $this->template->name);
                                if (\Illuminate\Support\Facades\Schema::hasColumn('events', 'program_start_place_id')) {
                                    $set('program_start_place_id', $this->template->start_place_id);
                                }

                                // Puste → 1; jawne 0 zostaje (wycieczka bez obsługi/kierowcy).
                                $staff = $get('staff_count');
                                if ($staff === null || $staff === '') {
                                    $set('staff_count', 1);
                                }
                                $driver = $get('driver_count');
                                if ($driver === null || $driver === '') {
                                    $set('driver_count', 1);
                                }

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
                            } else {
                                if (\Illuminate\Support\Facades\Schema::hasColumn('events', 'program_start_place_id')) {
                                    // Czyszczenie szablonu — zostaw program_start do ręcznego wyboru.
                                }
                            }

                            $this->refreshTotalCostFromTemplateState($set, $get);
                        })
                        ->helperText(fn (): string => $this->canCreateWithoutTemplate()
                            ? 'Szablon programu i bazowej kalkulacji. Imprezę bez szablonu mogą zakładać tylko admin / super_admin.'
                            : 'Wybór szablonu jest wymagany. Imprezę bez szablonu mogą zakładać tylko admin / super_admin.'),

                    // Wartości z szablonu — bez UI na Create (Operacje / Finanse po zapisie).
                    Forms\Components\Hidden::make('bus_id')->dehydrated(),
                    Forms\Components\Hidden::make('markup_id')->dehydrated(),
                    Forms\Components\Hidden::make('total_cost')
                        ->default(0)
                        ->dehydrated(),
                ]),

            ...EventKeyInfoFields::identitySection(),

            Forms\Components\Section::make('Grupa i miejsce startu')
                ->icon('heroicon-o-users')
                ->description('Potrzebne do utworzenia z szablonu i wstępnej kalkulacji. Resztę parametrów operacyjnych uzupełnisz później.')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    ...EventKeyInfoFields::participantFields(
                        onUpdated: fn (callable $get, callable $set) => $this->refreshTotalCostFromTemplateState($set, $get),
                    ),
                    ...EventKeyInfoFields::placeAndDistanceFields(
                        onUpdated: fn (callable $get, callable $set) => $this->refreshTotalCostFromTemplateState($set, $get),
                        includeProgramStartPlace: true,
                    ),
                ]),

            Forms\Components\Section::make('Uwagi')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->collapsible()
                ->collapsed()
                ->schema([
                    EventNotesFields::generalNotes()
                        ->hiddenLabel()
                        ->columnSpanFull(),
                    EventNotesFields::officeNotes()
                        ->hiddenLabel()
                        ->columnSpanFull(),
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

    protected function getRedirectUrl(): string
    {
        return EventResource::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Utworzono zapytanie')
            ->body('Jesteś na Podsumowaniu — uzupełnij gotowość, potem Program, Operacje i Finanse.');
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

        if (blank($this->data['event_template_id'] ?? null) && ! $this->canCreateWithoutTemplate()) {
            $errors['event_template_id'] = 'Wybór szablonu jest wymagany. Imprezę bez szablonu mogą zakładać tylko admin / super_admin.';
        }

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
            $parties = $this->resolvedOrderingParties();
            if ($parties !== []) {
                $attrs = app(\App\Services\EventOrderingPartyService::class)->primaryClientAttributes($parties);
                $data['client_name'] = $attrs['client_name'] ?? $data['client_name'] ?? null;
                $data['client_email'] = $attrs['client_email'] ?? $data['client_email'] ?? null;
                $data['client_phone'] = $attrs['client_phone'] ?? $data['client_phone'] ?? null;
            }
        }

        $resolvedParties = $this->resolvedOrderingParties();
        if ($resolvedParties !== []) {
            $data['ordering_parties'] = $resolvedParties;
        }

        if (blank($data['event_template_id'] ?? null) && ! $this->canCreateWithoutTemplate()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.event_template_id' => ['Wybór szablonu jest wymagany. Imprezę bez szablonu mogą zakładać tylko admin / super_admin.'],
            ]);
        }

        // Puste pole → 1; jawne 0 = wycieczka bez obsługi / bez kierowcy.
        $data['staff_count'] = Event::normalizeOperationalCount($data['staff_count'] ?? null);
        $data['driver_count'] = Event::normalizeOperationalCount($data['driver_count'] ?? null);

        return $data;
    }

    protected function canCreateWithoutTemplate(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasRole(['admin', 'super_admin']);
    }

    protected function syncClientFieldsFromOrderingParties(): void
    {
        if (filled($this->data['client_name'] ?? null)) {
            return;
        }

        $parties = $this->resolvedOrderingParties();

        if ($parties === []) {
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
            Notification::make()
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

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $pilotBirth = $data['pilot_birth_date'] ?? null;
        $pilotPesel = $data['pilot_pesel'] ?? null;
        $pilotPhone = $data['pilot_phone'] ?? null;
        $pilotContractorId = Schema::hasColumn('events', 'pilot_contractor_id')
            ? (filled($data['pilot_contractor_id'] ?? null) ? (int) $data['pilot_contractor_id'] : null)
            : null;
        $orderingParties = $this->resolvedOrderingParties();
        if ($orderingParties === []) {
            $orderingParties = $data['ordering_parties'] ?? null;
        }
        $orderingContractorIds = $data['orderingContractors'] ?? null;
        unset(
            $data['ordering_parties'],
            $data['orderingContractors'],
            $data['pilot_birth_date'],
            $data['pilot_pesel'],
            $data['pilot_phone'],
            $data['pilot_contractor_search_all'],
        );

        if (Schema::hasColumn('events', 'pilot_contractor_id') && $pilotContractorId) {
            $contractor = \App\Models\Contractor::query()->find($pilotContractorId);
            $data['assigned_to'] = app(PilotContractorAssignmentService::class)->resolvePortalUserId($contractor);
        }

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
                'staff_count' => $data['staff_count'] ?? null,
                'driver_count' => $data['driver_count'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'duration_days' => $data['duration_days'] ?? null,
                'start_place_id' => $data['start_place_id'] ?? null,
            ]);

            $event = Event::createFromTemplate($template, array_merge($data, [
                'ordering_parties' => $orderingParties,
                'orderingContractors' => $orderingContractorIds,
            ]));
            Log::info('CreateEvent:web:after_create_from_template', ['event_id' => $event->id]);

            $this->syncPilotAfterCreate($event, $pilotContractorId, $pilotBirth, $pilotPesel, $pilotPhone);

            return $event;
        }

        unset($data['orderingContractors'], $data['ordering_parties']);

        $event = parent::handleRecordCreation($data);

        if ($event instanceof Event && is_array($orderingParties) && $orderingParties !== []) {
            $event->syncOrderingParties($orderingParties);
        } elseif ($event instanceof Event && is_array($orderingContractorIds) && $orderingContractorIds !== []) {
            $event->syncOrderingContractors($orderingContractorIds);
        }

        if (
            $event instanceof Event
            && (
                array_key_exists('gratis_count', $data)
                || array_key_exists('staff_count', $data)
                || array_key_exists('driver_count', $data)
            )
        ) {
            try {
                $event->syncQtyVariantForGroup(
                    (int) ($data['participant_count'] ?? 1),
                    (int) ($data['gratis_count'] ?? 0),
                    array_key_exists('staff_count', $data)
                        ? Event::normalizeOperationalCount($data['staff_count'])
                        : null,
                    array_key_exists('driver_count', $data)
                        ? Event::normalizeOperationalCount($data['driver_count'])
                        : null,
                );
            } catch (\Throwable $e) {
                Log::warning('CreateEvent:web:sync_qty_variant_failed_no_template', [
                    'event_id' => $event->id,
                    'participant_count' => $data['participant_count'] ?? null,
                    'gratis_count' => $data['gratis_count'] ?? null,
                    'staff_count' => $data['staff_count'] ?? null,
                    'driver_count' => $data['driver_count'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->syncPilotAfterCreate($event, $pilotContractorId, $pilotBirth, $pilotPesel, $pilotPhone);

        return $event;
    }

    protected function syncPilotAfterCreate(
        Event $event,
        ?int $pilotContractorId,
        mixed $pilotBirth,
        ?string $pilotPesel,
        ?string $pilotPhone,
    ): void {
        $assignmentService = app(PilotContractorAssignmentService::class);

        if (Schema::hasColumn('events', 'pilot_contractor_id') && $pilotContractorId) {
            $assignmentService->syncContractorDemographics($pilotContractorId, $pilotBirth, $pilotPesel);
            $assignmentService->syncPortalUserDemographicsFromContractor(
                $pilotContractorId,
                filled($event->assigned_to) ? (int) $event->assigned_to : null,
            );

            return;
        }

        User::syncPilotDemographics($event->assigned_to, $pilotBirth, $pilotPesel, $pilotPhone);
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
