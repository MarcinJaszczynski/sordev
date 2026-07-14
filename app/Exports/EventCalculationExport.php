<?php

namespace App\Exports;

use App\Models\Event;
use App\Services\EventCalculationPresenter;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class EventCalculationExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private Event $event,
    ) {}

    public function title(): string
    {
        return 'Kalkulacja';
    }

    public function headings(): array
    {
        return ['Pole', 'Wartość'];
    }

    public function array(): array
    {
        $presenter = EventCalculationPresenter::for($this->event);
        $state = $presenter->widgetState();
        $calc = $state['calculations'] ?? [];
        $eventData = $calc['event_data'] ?? [];

        $rows = [
            ['Impreza', $this->event->name],
            ['Kod', $this->event->code ?? ''],
            ['Klient', $this->event->client_name ?? ''],
            ['Uczestnicy', (int) ($this->event->participant_count ?? 0)],
            ['Szablon', $eventData['template_name'] ?? ''],
            ['Autokar', $eventData['bus_name'] ?? ''],
            ['Transfer km', (float) ($this->event->transfer_km ?? 0)],
            ['Program km', (float) ($this->event->program_km ?? 0)],
            ['Km transportu', (float) ($state['event_transport_km'] ?? 0)],
            ['Koszt programu PLN', (float) ($calc['total_program_cost'] ?? 0)],
            ['Koszt transportu PLN', (float) ($state['transport_cost'] ?? 0)],
            ['Koszt łącznie PLN', (float) ($calc['total_cost'] ?? 0)],
            ['Plan kalkulacji PLN', $presenter->plannedTotalPln()],
            ['Plan rozliczenia PLN', $presenter->settlementPlannedPln()],
            ['Różnica PLN', $presenter->marginDeltaPln()],
            ['Różnica %', $presenter->marginDeltaPercent() ?? ''],
        ];

        foreach ($state['price_rows'] ?? [] as $row) {
            $rows[] = [
                sprintf('Cena/os (wariant %s)', $row->eventTemplateQty?->qty ?? '?'),
                (float) ($row->price_per_person ?? 0),
            ];
        }

        $qty = max(1, (int) ($this->event->participant_count ?? 1));
        $points = $state['detailed_calculations'][$qty]['PLN']['points'] ?? [];
        foreach ($points as $point) {
            $rows[] = [
                'Punkt: '.($point['name'] ?? ''),
                (float) ($point['cost'] ?? 0),
            ];
        }

        return $rows;
    }
}
