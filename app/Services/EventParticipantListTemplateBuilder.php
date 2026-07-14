<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventParticipantListTemplateBuilder
{
    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Imię', 'Nazwisko', 'Data urodzenia', 'PESEL', 'E-mail', 'Telefon', 'Nr rezerwacji'];
    }

    /** @return array<int, array{0: string, 1: string}> */
    public function instructionLines(): array
    {
        return [
            ['INSTRUKCJA — lista uczestników (ubezpieczenie i weryfikacja danych)'],
            [''],
            ['Wypełnij kolumny Imię, Nazwisko i Data urodzenia (format: RRRR-MM-DD lub DD.MM.RRRR).'],
            ['Pozostałe kolumny są opcjonalne.'],
            ['Możesz też użyć jednej kolumny „Imię i nazwisko” zamiast osobnych Imię/Nazwisko.'],
            [''],
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function exampleRows(): array
    {
        return [
            ['Jan', 'Kowalski', '2010-05-15', '', 'jan@example.com', '', ''],
            ['Anna', 'Nowak', '12.03.2011', '', '', '500600700', 'REZ-001'],
        ];
    }

    public function downloadCsv(Event $event): StreamedResponse
    {
        $filename = Str::slug($event->name).'-lista-uczestnikow-szablon.csv';

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            foreach ($this->instructionLines() as $line) {
                fputcsv($out, $line, ';');
            }

            fputcsv($out, $this->headings(), ';');

            foreach ($this->exampleRows() as $row) {
                fputcsv($out, $row, ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
