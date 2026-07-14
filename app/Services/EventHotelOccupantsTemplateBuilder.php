<?php

namespace App\Services;

use App\Models\Event;
use App\Support\EventHotelPlanFormatting;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventHotelOccupantsTemplateBuilder
{
    public function __construct(
        private readonly EventHotelPlanService $planService,
    ) {}

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Noc', 'Hotel', 'Typ pokoju', 'Pokój', 'Miejsce', 'Imię i nazwisko'];
    }

    /** @return array<int, array{0: string, 1: string}> */
    public function instructionLines(): array
    {
        return [
            ['INSTRUKCJA — lista miejsc noclegowych (1 wiersz = 1 osoba)'],
            [''],
            ['Najpierw uzupełnij strukturę pokoi w systemie (krok 1).'],
            ['Każdy wiersz to jedno miejsce: 10 pokoi 3-osobowych = 30 wierszy, 2 pokoje 2-osobowe = 4 wiersze itd.'],
            ['Wypełnij kolumnę „Imię i nazwisko”. Kolumny Noc–Miejsce zostaw bez zmian.'],
            [''],
        ];
    }

    /**
     * @return array<int, array<int, int|string>>
     */
    public function dataRows(Event $event): array
    {
        $this->planService->ensureStaysForEvent($event);
        $this->planService->syncAllRoomUnitsForEvent($event);
        $event->refresh()->load(['hotelStays.contractor', 'hotelStays.roomLines.hotelRoom', 'hotelStays.roomLines.units', 'hotelStays.roomLines.occupants']);

        $staysPayload = collect($this->planService->staysToPayload($event))->keyBy('day');
        $rows = [];

        foreach ($event->hotelStays as $stay) {
            $hotelName = $stay->contractor?->name ?? '';
            $stayPayload = $staysPayload->get($stay->day, ['room_lines' => []]);

            if (($stayPayload['room_lines'] ?? []) === []) {
                $rows[] = [$stay->day, $hotelName, '— uzupełnij strukturę —', '', '', ''];

                continue;
            }

            $hotelRoomsById = $stay->roomLines->pluck('hotelRoom')->filter()->keyBy('id');

            foreach (EventHotelPlanFormatting::expandedPersonSlots($stayPayload, $hotelRoomsById) as $slot) {
                $rows[] = [
                    $stay->day,
                    $hotelName,
                    $slot['room_type'],
                    $slot['total_units'] > 1 ? "{$slot['unit_index']}/{$slot['total_units']}" : '1/1',
                    $slot['beds_per_room'] > 1 ? "{$slot['bed_index']}/{$slot['beds_per_room']}" : '1/1',
                    trim((string) ($slot['occupant']['name'] ?? '')),
                ];
            }
        }

        return $rows;
    }

    public function downloadCsv(Event $event): StreamedResponse
    {
        $filename = Str::slug($event->name).'-lista-osob-noclegi.csv';

        return response()->streamDownload(function () use ($event): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            foreach ($this->instructionLines() as $line) {
                fputcsv($out, ['# '.($line[0] ?? '')], ';');
            }

            fputcsv($out, $this->headings(), ';');

            foreach ($this->dataRows($event) as $row) {
                fputcsv($out, $row, ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
