<?php

namespace App\Filament\Resources\EventResource\Concerns;

use App\Filament\Concerns\HasWorkflowRecordContext;
use App\Filament\Concerns\InteractsWithTaskEditModal;
use App\Filament\Resources\ContractorResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventTemplateResource;
use App\Models\Contact;
use App\Models\Contractor;
use App\Models\Event;
use App\Support\ContractorContactDetails;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

trait HasEventWorkflowContext
{
    use HasWorkflowRecordContext;
    use InteractsWithTaskEditModal;

    /** Wymusza re-render górnego paska workflow (kontakty / meta) po zmianach finansowych. */
    public int $workflowFinanceTick = 0;

    #[On('event-workflow-finance-changed')]
    public function refreshWorkflowFinanceBar(): void
    {
        $this->workflowFinanceTick++;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createTaskDefaultFormData(): array
    {
        if (! isset($this->record) || ! $this->record instanceof Event) {
            return [];
        }

        return [
            'taskable_type' => Event::class,
            'taskable_id' => (int) $this->record->getKey(),
        ];
    }

    public function openEventCreateTaskModal(): void
    {
        $this->openCreateTaskModal($this->createTaskDefaultFormData());
    }

    public function getWorkflowContext(): ?array
    {
        if (! isset($this->record) || ! $this->record instanceof Event) {
            return null;
        }

        /** @var Event $event */
        $event = $this->record;
        $event->loadMissing([
            'eventTemplate',
            'startPlace',
            'pilotContractor',
            'assignedUser',
            'officeCaretaker',
            'driverContractor',
            'transportContractor',
            'hotelStays.contractor',
            'hotelStays.contractorLocation',
            'orderingContractors',
        ]);

        $start = $event->start_date?->format('d.m.Y');
        $end = $event->end_date?->format('d.m.Y');
        $startPlace = $event->startPlace?->name;
        $pickupDetails = $this->formatWorkflowPickupPlace($event);
        $wyjazdLabel = $this->formatWorkflowWyjazdLabel($startPlace, $pickupDetails);
        $substitutionTime = $this->formatWorkflowTime($event->substitution_time ?? null);
        $returnTime = $this->formatWorkflowTime($event->return_time ?? null);

        $substitutionLabel = collect([$start, $substitutionTime])->filter()->implode(' ');
        $returnLabel = collect([$end ?: $start, $returnTime])->filter()->implode(' ');

        $termin = $start ?: '—';
        if ($end && $end !== $start) {
            $termin = "{$start} – {$end}";
        }
        if ($wyjazdLabel) {
            $termin .= ' · '.$wyjazdLabel;
        }

        $links = [];
        if ($event->event_template_id) {
            $links[] = [
                'label' => 'Szablon',
                'url' => EventTemplateResource::getUrl('edit', ['record' => $event->event_template_id]),
                'icon' => 'heroicon-o-rectangle-stack',
            ];
        }

        $links[] = [
            'label' => 'Oferta Word',
            'url' => route('admin.events.offer.word', $event),
            'icon' => 'heroicon-o-document-arrow-down',
            'external' => true,
        ];

        $links[] = [
            'label' => 'Nowe zadanie',
            'wire_click' => 'openEventCreateTaskModal',
            'icon' => 'heroicon-o-plus-circle',
        ];

        $statusLabel = Event::getStatusOptions()[$event->status] ?? $event->status;

        $participants = (int) ($event->participant_count ?? 0);
        $gratis = $event->resolveGratisCountForParticipantCount($participants);
        $participantsDisplay = $gratis > 0 ? "{$participants}+{$gratis}" : (string) $participants;

        $meta = [];
        foreach ($this->eventWorkflowContactMeta($event) as $item) {
            $meta[] = $item;
        }

        return [
            'type' => 'Impreza',
            'title' => $event->name ?? 'Impreza #'.$event->id,
            'title_url' => EventResource::getUrl('edit', ['record' => $event->getKey()]),
            'subtitle' => $termin,
            'code' => $event->code ?: null,
            'participants' => $participantsDisplay,
            'start_date' => $start,
            'end_date' => $end,
            'start_place' => $wyjazdLabel,
            'start_place_catalog' => $startPlace,
            'pickup_place_details' => $pickupDetails,
            'substitution_label' => $substitutionLabel !== '' ? $substitutionLabel : null,
            'return_label' => $returnLabel !== '' ? $returnLabel : null,
            'status' => $statusLabel,
            'statusColor' => Event::statusBadgeColor($event->status),
            'meta' => $meta,
            // Finanse (dostawcy / klienci / narzut) — tylko belka na stronie Finanse.
            'finance' => null,
            'links' => $links,
        ];
    }

    /**
     * Kontakty operacyjne do boxa „Impreza” (zamawiający, pilot, transport, hotel…).
     *
     * @return list<array{label: string, value: string, hint?: string, url?: string}>
     */
    protected function eventWorkflowContactMeta(Event $event): array
    {
        $items = [];

        $ordering = $this->formatWorkflowOrderingPartyMeta($event);
        if ($ordering !== null) {
            $items[] = $ordering;
        }

        $caretaker = $this->formatWorkflowPersonMeta(
            $event->officeCaretaker?->name,
            filled($event->officeCaretaker?->phone) ? (string) $event->officeCaretaker->phone : null,
        );
        if ($caretaker !== null) {
            $items[] = array_merge(['label' => 'Opiekun imprezy'], $caretaker);
        }

        $pilot = $this->formatWorkflowContractorMeta(
            $event->pilotContractor,
            fallbackName: $event->assignedUser?->name,
            fallbackPhone: filled($event->assignedUser?->phone) ? (string) $event->assignedUser->phone : null,
        );
        if ($pilot !== null) {
            $items[] = array_merge(['label' => 'Pilot'], $pilot);
        }

        $transport = $this->formatWorkflowTransportMeta($event);
        if ($transport !== null) {
            $items[] = $transport;
        }

        $driver = $this->formatWorkflowPersonMeta(
            filled($event->driver_name)
                ? (string) $event->driver_name
                : ($event->driverContractor?->displayLabel() ?: null),
            filled($event->driver_phone)
                ? (string) $event->driver_phone
                : (filled($event->driverContractor?->phone) ? (string) $event->driverContractor->phone : null),
        );
        if ($driver !== null) {
            $driverMeta = $driver;
            if (filled($event->driver_contractor_id)) {
                $driverMeta['url'] = ContractorResource::getUrl('edit', ['record' => $event->driver_contractor_id]);
            }
            $items[] = array_merge(['label' => 'Kierowca'], $driverMeta);
        }

        $hotel = $this->formatWorkflowHotelMeta($event);
        if ($hotel !== null) {
            $items[] = $hotel;
        }

        return $items;
    }

    /**
     * @return array{label: string, value: string, hint?: string, url?: string}|null
     */
    protected function formatWorkflowOrderingPartyMeta(Event $event): ?array
    {
        $trip = app(\App\Services\EventOrderingPartyService::class)->tripContactForEvent($event);
        $contractor = $trip['contractor'] ?? $event->orderingContractors->first();

        if ($contractor instanceof Contractor) {
            $contact = $trip['contact'] ?? null;
            if (! $contact) {
                $contactId = (int) ($contractor->pivot->contact_id ?? 0);
                if ($contactId > 0) {
                    $contact = Contact::query()->find($contactId);
                }
            }

            $meta = ContractorContactDetails::operationalMeta($contractor, null, $contact);
            $extra = $event->orderingContractors->count() - 1;
            $value = (string) ($meta['company_name'] ?? $contractor->displayLabel());
            if ($extra > 0) {
                $value .= " (+{$extra})";
            }

            $hint = $this->joinWorkflowHints([
                $meta['contact_name'] ?? null,
                $meta['phone'] ?? null,
                $meta['email'] ?? null,
                $meta['address'] ?? null,
                Schema::hasColumn('event_contractor', 'goes_on_trip')
                    && $event->orderingContractors->contains(fn (Contractor $c): bool => (bool) ($c->pivot->goes_on_trip ?? false))
                    ? 'kontakt na wyjeździe'
                    : null,
            ]);

            return array_filter([
                'label' => 'Zamawiający',
                'value' => $value,
                'hint' => $hint,
                'url' => ContractorResource::getUrl('edit', ['record' => $contractor->getKey()]),
            ], fn ($v): bool => $v !== null && $v !== '');
        }

        $client = $this->formatWorkflowPersonMeta(
            filled($event->client_name) ? (string) $event->client_name : null,
            filled($event->client_phone) ? (string) $event->client_phone : null,
        );
        if ($client === null) {
            return null;
        }

        if (filled($event->client_email)) {
            $client['hint'] = $this->joinWorkflowHints([
                $client['hint'] ?? null,
                trim((string) $event->client_email),
            ]);
        }

        return array_merge(['label' => 'Zamawiający'], $client);
    }

    /**
     * @return array{label: string, value: string, hint?: string, url?: string}|null
     */
    protected function formatWorkflowTransportMeta(Event $event): ?array
    {
        $contractor = $event->transportContractor;
        if ($contractor instanceof Contractor) {
            $location = $contractor->usesBusinessLocations()
                ? $contractor->defaultLocation()
                : null;
            $meta = ContractorContactDetails::operationalMeta($contractor, $location);
            $hint = $this->joinWorkflowHints([
                $meta['address'] ?? null,
                $meta['phone'] ?? null,
                $meta['email'] ?? null,
                filled($event->driver_name) ? 'kierowca: '.$event->driver_name : null,
                filled($event->vehicle_registration) ? 'rej. '.$event->vehicle_registration : null,
            ]);

            return array_filter([
                'label' => 'Transport',
                'value' => (string) ($meta['company_name'] ?? $contractor->displayLabel()),
                'hint' => $hint,
                'url' => ContractorResource::getUrl('edit', ['record' => $contractor->getKey()]),
            ], fn ($v): bool => $v !== null && $v !== '');
        }

        if (filled($event->transport_company_name)) {
            $hint = $this->joinWorkflowHints([
                filled($event->driver_name) ? 'kierowca: '.$event->driver_name : null,
                filled($event->driver_phone) ? (string) $event->driver_phone : null,
                filled($event->vehicle_registration) ? 'rej. '.$event->vehicle_registration : null,
            ]);

            return array_filter([
                'label' => 'Transport',
                'value' => trim((string) $event->transport_company_name),
                'hint' => $hint,
            ], fn ($v): bool => $v !== null && $v !== '');
        }

        return null;
    }

    /**
     * @return array{label: string, value: string, hint?: string, url?: string}|null
     */
    protected function formatWorkflowHotelMeta(Event $event): ?array
    {
        $stays = $event->hotelStays
            ->filter(fn ($stay) => filled($stay->contractor_id))
            ->values();

        if ($stays->isEmpty()) {
            return null;
        }

        $first = $stays->sortBy('day')->first();
        $contractor = $first?->contractor;
        if (! $contractor instanceof Contractor) {
            return null;
        }

        $meta = ContractorContactDetails::operationalMeta($contractor, $first->contractorLocation);
        $uniqueContractors = $stays
            ->map(fn ($stay) => (int) $stay->contractor_id)
            ->unique()
            ->values();
        $extra = $uniqueContractors->count() - 1;
        $value = (string) ($meta['company_name'] ?? $contractor->displayLabel());
        if ($extra > 0) {
            $value .= " (+{$extra})";
        }

        $hint = $this->joinWorkflowHints([
            $meta['branch_name'] ?? null,
            $meta['address'] ?? null,
            $meta['phone'] ?? null,
            $meta['email'] ?? null,
        ]);

        return array_filter([
            'label' => 'Hotel',
            'value' => $value,
            'hint' => $hint,
            'url' => ContractorResource::getUrl('edit', ['record' => $contractor->getKey()]),
        ], fn ($v): bool => $v !== null && $v !== '');
    }

    /**
     * @return array{value: string, hint?: string, url?: string}|null
     */
    protected function formatWorkflowContractorMeta(
        ?Contractor $contractor,
        ?string $fallbackName = null,
        ?string $fallbackPhone = null,
    ): ?array {
        if ($contractor instanceof Contractor) {
            $meta = ContractorContactDetails::operationalMeta($contractor);
            $hint = $this->joinWorkflowHints([
                $meta['phone'] ?? null,
                $meta['email'] ?? null,
                $meta['address'] ?? null,
            ]);

            return array_filter([
                'value' => (string) ($meta['company_name'] ?? $contractor->displayLabel()),
                'hint' => $hint,
                'url' => ContractorResource::getUrl('edit', ['record' => $contractor->getKey()]),
            ], fn ($v): bool => $v !== null && $v !== '');
        }

        return $this->formatWorkflowPersonMeta($fallbackName, $fallbackPhone);
    }

    /**
     * @return array{value: string, hint?: string}|null
     */
    protected function formatWorkflowPersonMeta(?string $name, ?string $phone): ?array
    {
        $name = filled($name) ? trim($name) : null;
        $phone = filled($phone) ? trim($phone) : null;

        if ($name === null && $phone === null) {
            return null;
        }

        if ($name !== null && $phone !== null) {
            return ['value' => $name, 'hint' => $phone];
        }

        return ['value' => $name ?? $phone];
    }

    /**
     * @param  list<string|null>  $parts
     */
    protected function joinWorkflowHints(array $parts): ?string
    {
        $joined = collect($parts)
            ->map(fn ($part) => filled($part) ? trim((string) $part) : null)
            ->filter()
            ->unique()
            ->implode(' · ');

        return $joined !== '' ? $joined : null;
    }

    /**
     * Faktyczne miejsce podstawienia (adres / szczegóły), niezależnie od Place z kalkulacji.
     */
    protected function formatWorkflowPickupPlace(Event $event): ?string
    {
        $candidates = [];

        if (filled($event->pickup_place_details)) {
            $plain = trim(html_entity_decode(strip_tags((string) $event->pickup_place_details)));
            $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
            if ($plain !== '') {
                $candidates[] = $plain;
            }
        }

        if (filled($event->adress_transport_start)) {
            $address = trim((string) $event->adress_transport_start);
            if ($address !== '') {
                $candidates[] = $address;
            }
        }

        $candidates = array_values(array_unique($candidates));

        return $candidates[0] ?? null;
    }

    /**
     * Etykieta „wyjazd z”: Place z kalkulacji + faktyczne podstawienie (gdy inne).
     */
    protected function formatWorkflowWyjazdLabel(?string $startPlace, ?string $pickupDetails): ?string
    {
        $startPlace = filled($startPlace) ? trim($startPlace) : null;
        $pickupDetails = filled($pickupDetails) ? trim($pickupDetails) : null;

        if ($startPlace && $pickupDetails) {
            if (mb_stripos($pickupDetails, $startPlace) !== false
                || mb_stripos($startPlace, $pickupDetails) !== false
                || mb_strtolower($startPlace) === mb_strtolower($pickupDetails)
            ) {
                return $pickupDetails;
            }

            return $startPlace.' → '.$pickupDetails;
        }

        return $pickupDetails ?: $startPlace;
    }

    protected function formatWorkflowTime(mixed $time): ?string
    {
        if (! filled($time)) {
            return null;
        }

        return substr((string) $time, 0, 5);
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        if (method_exists($this, 'buildModuleBreadcrumbs')) {
            /** @var callable(): array<int|string, string> $builder */
            $builder = [$this, 'buildModuleBreadcrumbs'];

            return $builder();
        }

        return $this->eventRecordBreadcrumbs();
    }

    /**
     * Breadcrumbs: Imprezy › {nazwa} › [moduł] › [sekcja].
     *
     * @return array<int|string, string>
     */
    protected function eventRecordBreadcrumbs(?string $moduleLabel = null, ?string $moduleUrl = null, ?string $sectionLabel = null): array
    {
        if (! isset($this->record) || ! $this->record instanceof Event) {
            return [
                EventResource::getUrl('index') => 'Imprezy',
            ];
        }

        /** @var Event $event */
        $event = $this->record;

        $breadcrumbs = [
            EventResource::getUrl('index') => 'Imprezy',
            EventResource::getUrl('edit', ['record' => $event->getKey()]) => Str::limit((string) ($event->name ?: 'Impreza'), 48),
        ];

        if (filled($moduleLabel) && filled($moduleUrl)) {
            $breadcrumbs[$moduleUrl] = $moduleLabel;
        }

        if (filled($sectionLabel)) {
            $breadcrumbs[] = $sectionLabel;
        }

        return $breadcrumbs;
    }
}
