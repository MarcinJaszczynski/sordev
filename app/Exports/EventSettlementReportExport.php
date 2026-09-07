<?php

namespace App\Exports;

use App\Models\Event;
use App\Services\EventSettlementReportService;
use App\Services\SettlementPaymentHealthService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class EventSettlementReportExport implements WithMultipleSheets
{
    public function __construct(
        private Event $event,
    ) {}

    public function sheets(): array
    {
        $report = app(EventSettlementReportService::class)->build($this->event);

        return [
            new EventSettlementReportSummarySheet($report),
            new EventSettlementReportCostsSheet($report),
            new EventSettlementReportParticipantsSheet($report),
        ];
    }
}

final class EventSettlementReportSummarySheet implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithTitle
{
    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Podsumowanie';
    }

    public function headings(): array
    {
        return ['Pole', 'Wartość'];
    }

    public function array(): array
    {
        $event = $this->report['event'] ?? [];
        $settlement = $this->report['settlement'] ?? [];
        $office = $this->report['dashboard']['office'] ?? [];
        $pilot = $this->report['dashboard']['pilot'] ?? [];
        $labels = $this->report['summary_labels'] ?? [];

        return [
            ['Impreza', ($event['code'] ?? '').' — '.($event['name'] ?? '')],
            ['Uczestnicy', (int) ($event['participant_count'] ?? 0)],
            ['Plan kosztów', $labels['planned_cost'] ?? ''],
            ['Zapłacono dostawcom', $labels['actual_cost'] ?? ''],
            ['Należne od klientów', $labels['participant_due'] ?? ''],
            ['Wpłacono od klientów', $labels['participant_paid'] ?? ''],
            ['Wynik netto', $labels['net_result'] ?? ''],
            ['Biuro — plan', $office['planned_label'] ?? ''],
            ['Biuro — wpłacono', $office['paid_label'] ?? ''],
            ['Biuro — brakuje', $office['remaining_label'] ?? ''],
            ['Pilot — plan', $pilot['planned_label'] ?? ''],
            ['Pilot — wpłacono', $pilot['paid_label'] ?? ''],
            ['Pilot — brakuje', $pilot['remaining_label'] ?? ''],
        ];
    }
}

final class EventSettlementReportCostsSheet implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithTitle
{
    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Koszty';
    }

    public function headings(): array
    {
        return ['Pozycja', 'Źródło', 'Płatnik', 'Planowane PLN', 'Zapłacono PLN', 'Brakuje PLN', 'Semafor'];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->report['dashboard']['control_rows'] ?? [] as $row) {
            $rows[] = [
                $row['name'] ?? '—',
                $row['source_type'] ?? '—',
                ($row['paid_by'] ?? 'office') === 'pilot' ? 'Pilot' : 'Biuro',
                number_format((float) ($row['planned_pln'] ?? 0), 2, ',', ' '),
                number_format((float) ($row['paid_pln'] ?? 0), 2, ',', ' '),
                number_format((float) ($row['remaining_pln'] ?? 0), 2, ',', ' '),
                SettlementPaymentHealthService::$statusLabels[$row['coverage_status'] ?? ''] ?? '—',
            ];
        }

        return $rows;
    }
}

final class EventSettlementReportParticipantsSheet implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithTitle
{
    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Wpłaty uczestników';
    }

    public function headings(): array
    {
        return ['Uczestnik', 'Referencja', 'Należne PLN', 'Wpłacono PLN', 'Brakuje PLN', 'Semafor', 'Następna rata'];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->report['participant_rows'] ?? [] as $row) {
            $rows[] = [
                $row['participant_name'] ?? '—',
                $row['booking_reference'] ?? '—',
                number_format((float) ($row['due_pln'] ?? 0), 2, ',', ' '),
                number_format((float) ($row['paid_pln'] ?? 0), 2, ',', ' '),
                number_format((float) ($row['remaining_pln'] ?? 0), 2, ',', ' '),
                $row['coverage_label'] ?? '—',
                $row['installment_label'] ?? '—',
            ];
        }

        return $rows;
    }
}
