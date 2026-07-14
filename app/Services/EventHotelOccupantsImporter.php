<?php

namespace App\Services;

use App\Models\Event;
use App\Models\HotelRoom;
use App\Support\EventHotelPlanFormatting;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class EventHotelOccupantsImporter
{
    public function __construct(
        private readonly EventHotelPlanService $planService,
    ) {}

    /**
     * @return array{imported: int, skipped: int, warnings: array<int, string>}
     */
    public function importFromPath(Event $event, string $path): array
    {
        $rows = $this->readRows($path);

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'Plik jest pusty lub nie zawiera danych.',
            ]);
        }

        [$headers, $dataRows] = $this->parseSheet($rows);

        $this->planService->ensureStaysForEvent($event);
        $this->planService->syncAllRoomUnitsForEvent($event);
        $event->load(['hotelStays.roomLines.hotelRoom', 'hotelStays.roomLines.occupants']);

        $stayPayloads = $this->planService->staysToPayload($event);
        $participants = collect($this->planService->availableParticipants($event));
        $hotelRoomsByName = HotelRoom::query()->get()->keyBy(fn (HotelRoom $room) => $this->normalizeKey($room->name));

        $imported = 0;
        $skipped = 0;
        $warnings = [];

        foreach ($dataRows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2;
            $values = collect($row)->values()->all();
            $mapped = [];

            foreach ($headers as $colIndex => $header) {
                if ($header === '') {
                    continue;
                }
                $mapped[$header] = trim((string) ($values[$colIndex] ?? ''));
            }

            if ($this->shouldSkipRow($mapped, $values)) {
                continue;
            }

            $day = $this->parseDay($mapped);
            $roomKey = $this->resolveRoomKey($mapped);
            $name = $this->resolveName($mapped);
            $unitIndex = $this->parseFractionIndex($mapped['pokoj'] ?? $mapped['nr_pokoju'] ?? '1/1');
            $bedIndex = $this->parseFractionIndex($mapped['miejsce'] ?? '1/1');

            if ($day === null || $roomKey === '' || $name === '') {
                $skipped++;
                $warnings[] = "Wiersz {$rowNumber}: uzupełnij Noc, Typ pokoju i Imię i nazwisko.";

                continue;
            }

            $stayIndex = collect($stayPayloads)->search(fn (array $stay) => (int) $stay['day'] === $day);
            if ($stayIndex === false) {
                $skipped++;
                $warnings[] = "Wiersz {$rowNumber}: brak nocy {$day} w planie imprezy.";

                continue;
            }

            $lineIndex = $this->findRoomLineIndex($stayPayloads[$stayIndex], $roomKey, $hotelRoomsByName);
            if ($lineIndex === null) {
                $skipped++;
                $warnings[] = "Wiersz {$rowNumber}: nie znaleziono pokoju „{$roomKey}” w nocy {$day}.";

                continue;
            }

            $line = $stayPayloads[$stayIndex]['room_lines'][$lineIndex];
            $maxUnits = max(1, (int) ($line['quantity'] ?? 1));
            $maxBeds = EventHotelPlanFormatting::linePeopleCount($line, $hotelRoomsByName);

            if ($unitIndex > $maxUnits || $bedIndex > $maxBeds) {
                $skipped++;
                $warnings[] = "Wiersz {$rowNumber}: miejsce {$unitIndex}/{$maxUnits} · {$bedIndex}/{$maxBeds} poza strukturą pokoi.";

                continue;
            }

            $participant = $this->matchParticipant($participants, $name);
            $occupantPayload = $participant
                ? [
                    'id' => null,
                    'name' => $participant['label'],
                    'source' => $participant['source'],
                    'unit_index' => $unitIndex,
                    'bed_index' => $bedIndex,
                    'event_agreement_id' => $participant['agreement_id'] ?? null,
                    'contract_id' => $participant['contract_id'] ?? null,
                    'reservation_id' => $participant['reservation_id'] ?? null,
                    'participant_key' => $participant['key'],
                ]
                : [
                    'id' => null,
                    'name' => $name,
                    'source' => 'manual',
                    'unit_index' => $unitIndex,
                    'bed_index' => $bedIndex,
                    'event_agreement_id' => null,
                    'contract_id' => null,
                    'reservation_id' => null,
                    'participant_key' => null,
                ];

            $existingOccupants = collect($stayPayloads[$stayIndex]['room_lines'][$lineIndex]['occupants'] ?? []);

            $duplicateParticipant = $existingOccupants->contains(function (array $occupant) use ($occupantPayload, $name, $unitIndex, $bedIndex) {
                if ((int) ($occupant['unit_index'] ?? 1) === $unitIndex && (int) ($occupant['bed_index'] ?? 1) === $bedIndex) {
                    return false;
                }

                if (! empty($occupantPayload['participant_key']) && ($occupant['participant_key'] ?? null) === $occupantPayload['participant_key']) {
                    return true;
                }

                return $this->normalizeKey((string) ($occupant['name'] ?? '')) === $this->normalizeKey($name);
            });

            if ($duplicateParticipant) {
                $skipped++;
                $warnings[] = "Wiersz {$rowNumber}: „{$name}” jest już przypisany/a w tej nocy.";

                continue;
            }

            $filtered = $existingOccupants
                ->reject(fn (array $occupant) => (int) ($occupant['unit_index'] ?? 1) === $unitIndex
                    && (int) ($occupant['bed_index'] ?? 1) === $bedIndex)
                ->values()
                ->all();

            $filtered[] = $occupantPayload;
            $stayPayloads[$stayIndex]['room_lines'][$lineIndex]['occupants'] = $filtered;
            $imported++;
        }

        if ($imported === 0 && $skipped > 0) {
            throw ValidationException::withMessages([
                'file' => 'Nie zaimportowano żadnych osób. '.implode(' ', array_slice($warnings, 0, 3)),
            ]);
        }

        if ($imported === 0) {
            throw ValidationException::withMessages([
                'file' => 'Brak wierszy z imieniem i nazwiskiem. Wypełnij kolumnę „Imię i nazwisko” w szablonie.',
            ]);
        }

        $this->planService->savePlan($event, $stayPayloads);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'warnings' => $warnings,
        ];
    }

    private function readRows(string $path): Collection
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return $this->readCsvRows($path);
        }

        $sheets = Excel::toCollection(null, $path);

        foreach ($sheets as $sheet) {
            if ($this->looksLikeDataSheet($sheet)) {
                return $sheet;
            }
        }

        return $sheets->first() ?? collect();
    }

    private function readCsvRows(string $path): Collection
    {
        $delimiter = $this->detectCsvDelimiter($path);
        $rows = collect();
        $handle = fopen($path, 'r');

        if (! $handle) {
            return $rows;
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows->push($data);
        }

        fclose($handle);

        return $rows;
    }

    private function detectCsvDelimiter(string $path): string
    {
        $sample = file_get_contents($path, false, null, 0, 4096) ?: '';
        $semicolon = substr_count($sample, ';');
        $comma = substr_count($sample, ',');

        return $semicolon >= $comma ? ';' : ',';
    }

    /**
     * @return array{0: array<int, string>, 1: Collection<int, mixed>}
     */
    private function parseSheet(Collection $rows): array
    {
        $headerIndex = $rows->search(fn ($row) => $this->looksLikeHeaderRow($row));

        if ($headerIndex === false) {
            throw ValidationException::withMessages([
                'file' => 'Nie znaleziono nagłówków. Użyj szablonu z kolumnami: Noc, Hotel, Typ pokoju, Pokój, Miejsce, Imię i nazwisko.',
            ]);
        }

        $headerRow = $rows[$headerIndex];
        $headers = collect($headerRow)->map(fn ($value) => $this->canonicalHeader((string) $value))->all();
        $dataRows = $rows->slice($headerIndex + 1)->values();

        return [$headers, $dataRows];
    }

    private function looksLikeDataSheet(Collection $sheet): bool
    {
        foreach ($sheet->take(15) as $row) {
            if ($this->looksLikeHeaderRow($row)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeHeaderRow(mixed $row): bool
    {
        if (! is_iterable($row)) {
            return false;
        }

        $headers = collect($row)
            ->map(fn ($value) => $this->canonicalHeader((string) $value))
            ->filter()
            ->values();

        return $headers->contains('noc')
            && ($headers->contains('osoba') || $headers->contains('typ_pokoju') || $headers->contains('pokoj'));
    }

    /**
     * @param  array<string, string>  $mapped
     * @param  array<int, mixed>  $values
     */
    private function shouldSkipRow(array $mapped, array $values): bool
    {
        $first = trim((string) ($values[0] ?? ''));

        if ($first !== '' && str_starts_with($first, '#')) {
            return true;
        }

        if (str_starts_with(mb_strtoupper($first), 'INSTRUKCJA')) {
            return true;
        }

        $name = $this->resolveName($mapped);
        if ($name === '') {
            return true;
        }

        $roomKey = $this->resolveRoomKey($mapped);
        if (str_contains(mb_strtolower($roomKey), 'uzupełnij strukturę') || str_contains(mb_strtolower($roomKey), 'dodaj pokój')) {
            return true;
        }

        return false;
    }

    private function canonicalHeader(string $value): string
    {
        $key = $this->normalizeKey($value);

        return match ($key) {
            'noc', 'dzien', 'dzien noclegu', 'night', 'day' => 'noc',
            'hotel', 'nazwa hotelu', 'obiekt' => 'hotel',
            'typ pokoju', 'typ', 'room type' => 'typ_pokoju',
            'pokoj', 'pokoj nr linii', 'room' => 'pokoj',
            'nr', 'nr pok', 'nr pokoju', 'numer pokoju' => 'nr_pokoju',
            'miejsce', 'miejsce w pokoju', 'bed', 'slot' => 'miejsce',
            'osoba', 'imie i nazwisko', 'uczestnik', 'name', 'participant', 'nazwisko' => 'osoba',
            default => $key,
        };
    }

    /**
     * @param  array<string, string>  $mapped
     */
    private function parseDay(array $mapped): ?int
    {
        $raw = $mapped['noc'] ?? $mapped['dzien'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        if (preg_match('/(\d+)/', $raw, $matches)) {
            $day = (int) $matches[1];

            return $day > 0 ? $day : null;
        }

        return null;
    }

    private function parseFractionIndex(string $value): int
    {
        if (preg_match('/^(\d+)\s*\/\s*\d+$/', trim($value), $matches)) {
            return max(1, (int) $matches[1]);
        }

        if (is_numeric($value)) {
            return max(1, (int) $value);
        }

        return 1;
    }

    /**
     * @param  array<string, string>  $mapped
     */
    private function resolveRoomKey(array $mapped): string
    {
        $type = trim((string) ($mapped['typ_pokoju'] ?? ''));
        $legacy = trim((string) ($mapped['pokoj'] ?? ''));
        $raw = $type !== '' ? $type : $legacy;

        return preg_replace('/\s*\(\d+\/\d+\)\s*$/', '', $raw) ?? $raw;
    }

    /**
     * @param  array<string, string>  $mapped
     */
    private function resolveName(array $mapped): string
    {
        return trim((string) ($mapped['osoba'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $stayPayload
     * @param  Collection<string, HotelRoom>  $hotelRoomsByName
     */
    private function findRoomLineIndex(array $stayPayload, string $roomKey, Collection $hotelRoomsByName): ?int
    {
        $normalizedRoomKey = EventHotelPlanFormatting::normalizeRoomMatchKey($roomKey);

        if (is_numeric($roomKey)) {
            $index = (int) $roomKey - 1;
            if (isset($stayPayload['room_lines'][$index])) {
                return (int) $index;
            }
        }

        foreach ($stayPayload['room_lines'] ?? [] as $index => $line) {
            $label = trim((string) ($line['label'] ?? ''));
            if ($label !== '' && $this->normalizeKey($label) === $normalizedRoomKey) {
                return (int) $index;
            }

            $roomId = $line['hotel_room_id'] ?? null;
            if ($roomId) {
                $room = HotelRoom::find($roomId);
                if ($room && $this->normalizeKey($room->name) === $normalizedRoomKey) {
                    return (int) $index;
                }
            }
        }

        $catalogRoom = $hotelRoomsByName->get($normalizedRoomKey);
        if ($catalogRoom) {
            foreach ($stayPayload['room_lines'] ?? [] as $index => $line) {
                if ((int) ($line['hotel_room_id'] ?? 0) === (int) $catalogRoom->id) {
                    return (int) $index;
                }
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $participants
     * @return array<string, mixed>|null
     */
    private function matchParticipant(Collection $participants, string $name): ?array
    {
        $normalized = $this->normalizeKey($name);

        $exact = $participants->first(fn (array $participant) => $this->normalizeKey((string) $participant['label']) === $normalized);
        if ($exact) {
            return $exact;
        }

        return $participants->first(function (array $participant) use ($normalized) {
            $label = $this->normalizeKey((string) $participant['label']);

            return Str::contains($label, $normalized) || Str::contains($normalized, $label);
        });
    }

    private function normalizeKey(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));

        return preg_replace('/\s+/', ' ', $value) ?? '';
    }
}
