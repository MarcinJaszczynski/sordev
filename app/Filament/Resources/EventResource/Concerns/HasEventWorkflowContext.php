<?php

namespace App\Filament\Resources\EventResource\Concerns;

use App\Filament\Concerns\HasWorkflowRecordContext;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventTemplateResource;
use App\Models\Event;
use App\Services\EventWorkflowFinanceSummaryService;
use Illuminate\Support\Str;

trait HasEventWorkflowContext
{
    use HasWorkflowRecordContext;

    public function getWorkflowContext(): ?array
    {
        if (! isset($this->record) || ! $this->record instanceof Event) {
            return null;
        }

        /** @var Event $event */
        $event = $this->record;
        $event->loadMissing(['eventTemplate', 'startPlace']);

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

        $statusLabel = Event::getStatusOptions()[$event->status] ?? $event->status;

        $participants = (int) ($event->participant_count ?? 0);
        $gratis = $event->resolveGratisCountForParticipantCount($participants);
        $participantsDisplay = $gratis > 0 ? "{$participants}+{$gratis}" : (string) $participants;

        $finance = app(EventWorkflowFinanceSummaryService::class)->forEvent($event);

        return [
            'type' => 'Impreza',
            'title' => $event->name ?? 'Impreza #'.$event->id,
            'title_url' => EventResource::getUrl('edit', ['record' => $event->getKey()]),
            'subtitle' => $termin.($event->startPlace?->name ? ' · '.$event->startPlace->name : ''),
            'status' => $statusLabel,
            'statusColor' => Event::statusBadgeColor($event->status),
            'meta' => [
                ['label' => 'Kod', 'value' => $event->code ?? '—'],
                ['label' => 'Uczestnicy', 'value' => $participantsDisplay],
            ],
            'finance' => $finance,
            'links' => $links,
        ];
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
