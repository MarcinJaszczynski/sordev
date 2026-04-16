<?php

namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EventIndividualAgreementsReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected Event $event,
    ) {
    }

    public function collection()
    {
        return collect($this->event->buildIndividualAgreementReport()['rows']);
    }

    public function headings(): array
    {
        return [
            'Nr umowy',
            'Uczestnik',
            'Płatnik',
            'Email płatnika',
            'Telefon płatnika',
            'Status umowy',
            'Status płatności',
            'Kwota należna',
            'Kwota wpłacona',
            'Kwota pozostała',
            'Waluta',
            'Data zawarcia',
            'Data płatności',
        ];
    }

    public function map($row): array
    {
        return [
            $row['agreement_number'],
            $row['participant_name'],
            $row['payer_name'],
            $row['payer_email'],
            $row['payer_phone'],
            $row['status_label'],
            $row['payment_status_label'],
            number_format((float) $row['amount_due'], 2, '.', ''),
            number_format((float) $row['amount_paid'], 2, '.', ''),
            number_format((float) $row['amount_remaining'], 2, '.', ''),
            $row['currency'],
            optional($row['signed_at'])->format('Y-m-d H:i:s'),
            optional($row['paid_at'])->format('Y-m-d H:i:s'),
        ];
    }
}