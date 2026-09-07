<?php

namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EventParticipantInsuranceExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected Event $event,
    ) {}

    public function collection()
    {
        return $this->event->activeParticipants()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Lp.',
            'Imię',
            'Nazwisko',
            'Płeć',
            'Data urodzenia',
            'PESEL',
            'E-mail',
            'Telefon',
            'Nr rezerwacji',
            'Źródło',
        ];
    }

    /**
     * @param  \App\Models\EventParticipant  $participant
     */
    public function map($participant): array
    {
        static $index = 0;
        $index++;

        return [
            $index,
            $participant->first_name,
            $participant->last_name,
            $participant->genderLabel(),
            $participant->birth_date?->format('Y-m-d'),
            $participant->pesel,
            $participant->email,
            $participant->phone,
            $participant->booking_reference,
            \App\Models\EventParticipant::$sources[$participant->source] ?? $participant->source,
        ];
    }
}
