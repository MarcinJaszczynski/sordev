<?php

namespace App\Filament\Resources\EventResource\Concerns;

use App\Filament\Concerns\HasWorkflowRecordContext;
use App\Filament\Concerns\InteractsWithTaskEditModal;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventTemplateResource;
use App\Models\Event;
use App\Services\EventWorkflowFinanceSummaryService;
use Illuminate\Support\Str;

trait HasEventWorkflowContext
{
    use HasWorkflowRecordContext;
    use InteractsWithTaskEditModal;

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
            'hotelStays.contractor',
        ]);

        $start = $event->start_date?->format('d.m.Y') ?? '—';
        $end = $event->end_date?->format('d.m.Y');
        $termin = $end && $end !== $start ? "{$start} – {$end}" : $start;

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

        $finance = app(EventWorkflowFinanceSummaryService::class)->forEvent($event);

        $meta = [
            ['label' => 'Kod', 'value' => $event->code ?? '—'],
            ['label' => 'Uczestnicy', 'value' => $participantsDisplay],
        ];

        foreach ($this->eventWorkflowContactMeta($event) as $item) {
            $meta[] = $item;
        }

        return [
            'type' => 'Impreza',
            'title' => $event->name ?? 'Impreza #'.$event->id,
            'title_url' => EventResource::getUrl('edit', ['record' => $event->getKey()]),
            'subtitle' => $termin.($event->startPlace?->name ? ' · '.$event->startPlace->name : ''),
            'status' => $statusLabel,
            'statusColor' => Event::statusBadgeColor($event->status),
            'meta' => $meta,
            'finance' => $finance,
            'links' => $links,
        ];
    }

    /**
     * Kontakty operacyjne do boxa „Impreza” (zamawiający, pilot, kierowca, hotel).
     *
     * @return list<array{label: string, value: string}>
     */
    protected function eventWorkflowContactMeta(Event $event): array
    {
        $items = [];

        $client = $this->formatWorkflowPerson(
            filled($event->client_name) ? (string) $event->client_name : null,
            filled($event->client_phone) ? (string) $event->client_phone : null,
        );
        if ($client !== null) {
            $items[] = ['label' => 'Zamawiający', 'value' => $client];
        }

        $caretaker = $this->formatWorkflowPerson(
            $event->officeCaretaker?->name,
            filled($event->officeCaretaker?->phone) ? (string) $event->officeCaretaker->phone : null,
        );
        if ($caretaker !== null) {
            $items[] = ['label' => 'Opiekun imprezy', 'value' => $caretaker];
        }

        $pilotName = $event->pilotContractor?->displayLabel()
            ?: ($event->assignedUser?->name ?: null);
        $pilotPhone = filled($event->pilotContractor?->phone)
            ? (string) $event->pilotContractor->phone
            : (filled($event->assignedUser?->phone) ? (string) $event->assignedUser->phone : null);
        $pilot = $this->formatWorkflowPerson($pilotName, $pilotPhone);
        if ($pilot !== null) {
            $items[] = ['label' => 'Pilot', 'value' => $pilot];
        }

        $driverName = filled($event->driver_name)
            ? (string) $event->driver_name
            : ($event->driverContractor?->displayLabel() ?: null);
        $driverPhone = filled($event->driver_phone)
            ? (string) $event->driver_phone
            : (filled($event->driverContractor?->phone) ? (string) $event->driverContractor->phone : null);
        $driver = $this->formatWorkflowPerson($driverName, $driverPhone);
        if ($driver !== null) {
            $items[] = ['label' => 'Kierowca', 'value' => $driver];
        }

        $hotel = $this->formatWorkflowHotelSummary($event);
        if ($hotel !== null) {
            $items[] = ['label' => 'Hotel', 'value' => $hotel];
        }

        return $items;
    }

    protected function formatWorkflowPerson(?string $name, ?string $phone): ?string
    {
        $name = filled($name) ? trim($name) : null;
        $phone = filled($phone) ? trim($phone) : null;

        if ($name === null && $phone === null) {
            return null;
        }

        if ($name !== null && $phone !== null) {
            return "{$name} · {$phone}";
        }

        return $name ?? $phone;
    }

    protected function formatWorkflowHotelSummary(Event $event): ?string
    {
        $names = $event->hotelStays
            ->map(fn ($stay) => $stay->contractor?->displayLabel())
            ->filter(fn ($name) => filled($name))
            ->map(fn ($name) => trim((string) $name))
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return null;
        }

        $first = (string) $names->first();
        $extra = $names->count() - 1;

        return $extra > 0 ? "{$first} (+{$extra})" : $first;
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
