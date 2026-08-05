<?php

namespace App\Filament\Resources\EventResource\Concerns;

use App\Filament\Concerns\HasWorkflowRecordContext;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventTemplateResource;
use App\Models\Event;
use App\Services\EventWorkflowFinanceSummaryService;

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
            'title_url' => EventResource::getUrl('settlement-summary', ['record' => $event->getKey()]),
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
}
