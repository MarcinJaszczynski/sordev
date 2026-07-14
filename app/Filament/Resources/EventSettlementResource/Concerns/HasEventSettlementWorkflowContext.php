<?php

namespace App\Filament\Resources\EventSettlementResource\Concerns;

use App\Filament\Concerns\HasWorkflowRecordContext;
use App\Filament\Resources\EventResource;
use App\Models\EventSettlement;

trait HasEventSettlementWorkflowContext
{
    use HasWorkflowRecordContext;

    public function getWorkflowContext(): ?array
    {
        if (! isset($this->record) || ! $this->record instanceof EventSettlement) {
            return null;
        }

        /** @var EventSettlement $settlement */
        $settlement = $this->record;
        $settlement->loadMissing('event');

        $event = $settlement->event;
        $statusLabel = EventSettlement::$statuses[$settlement->status] ?? $settlement->status;

        return [
            'type' => 'Rozliczenie imprezy',
            'title' => $event?->name ?? 'Rozliczenie #'.$settlement->id,
            'subtitle' => $event?->start_date?->format('d.m.Y'),
            'status' => $statusLabel,
            'statusColor' => match ($settlement->status) {
                'closed' => 'success',
                'active' => 'info',
                'draft' => 'warning',
                default => 'gray',
            },
            'links' => $event ? [[
                'label' => 'Impreza',
                'url' => EventResource::getUrl('edit', ['record' => $event->id]),
                'icon' => 'heroicon-o-calendar-days',
            ]] : [],
        ];
    }
}
