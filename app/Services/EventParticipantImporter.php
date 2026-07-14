<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventAgreement;
use App\Models\EventParticipant;
use App\Support\EventAgreementParticipant;
use App\Support\ParticipantNameMatcher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class EventParticipantImporter
{
    /**
     * @return array{imported: int, skipped: int, linked: int, warnings: array<int, string>, batch_key: string}
     */
    public function importFromPath(Event $event, string $path, string $mode = 'append'): array
    {
        $rows = $this->readRows($path);

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'Plik jest pusty lub nie zawiera danych.',
            ]);
        }

        [$headers, $dataRows] = $this->parseSheet($rows);
        $batchKey = now()->format('Y-m-d_His').'_'.Str::random(6);

        if ($mode === 'replace') {
            $event->participants()
                ->where('source', EventParticipant::SOURCE_IMPORT)
                ->delete();
        }

        $existing = $event->participants()->get();
        $agreements = $this->loadAgreements($event);

        $imported = 0;
        $skipped = 0;
        $linked = 0;
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

            $nameParts = $this->resolveNameParts($mapped);
            $firstName = $nameParts['first_name'];
            $lastName = $nameParts['last_name'];
            $fullName = ParticipantNameMatcher::fullName($firstName, $lastName);

            if ($fullName === '') {
                $skipped++;
                $warnings[] = "Wiersz {$rowNumber}: brak imienia i nazwiska.";

                continue;
            }

            $birthDate = $this->parseBirthDate($mapped['data_urodzenia'] ?? $mapped['birth_date'] ?? '');

            if ($this->isDuplicate($existing, $firstName, $lastName, $birthDate)) {
                $skipped++;
                $warnings[] = "Wiersz {$rowNumber}: „{$fullName}” jest już na liście.";

                continue;
            }

            $participant = new EventParticipant([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birth_date' => $birthDate,
                'pesel' => $this->normalizePesel($mapped['pesel'] ?? ''),
                'email' => $mapped['email'] ?? $mapped['e_mail'] ?? null,
                'phone' => $mapped['telefon'] ?? $mapped['phone'] ?? null,
                'booking_reference' => $mapped['nr_rezerwacji'] ?? $mapped['booking_reference'] ?? null,
                'source' => EventParticipant::SOURCE_IMPORT,
                'status' => EventParticipant::STATUS_ACTIVE,
                'import_batch_key' => $batchKey,
            ]);

            $match = $this->matchAgreement($agreements, $fullName, $birthDate);
            if ($match) {
                if ($match instanceof Contract) {
                    $participant->contract_id = $match->id;
                } else {
                    $participant->event_agreement_id = $match->id;
                }
                $linked++;
            }

            $event->participants()->save($participant);
            $existing->push($participant);
            $imported++;
        }

        if ($imported === 0 && $skipped > 0) {
            throw ValidationException::withMessages([
                'file' => 'Nie zaimportowano żadnych uczestników. '.implode(' ', array_slice($warnings, 0, 3)),
            ]);
        }

        if ($imported === 0) {
            throw ValidationException::withMessages([
                'file' => 'Brak wierszy z danymi uczestników. Użyj szablonu z kolumnami: Imię, Nazwisko, Data urodzenia.',
            ]);
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'linked' => $linked,
            'warnings' => $warnings,
            'batch_key' => $batchKey,
        ];
    }

    private function readRows(string $path): Collection
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'csv' || $extension === 'txt' || $extension === '') {
            $csvRows = $this->readCsvRows($path);
            if ($csvRows->isNotEmpty()) {
                return $csvRows;
            }

            if ($extension === 'csv' || $extension === 'txt') {
                return $csvRows;
            }
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
                'file' => 'Nie znaleziono nagłówków. Użyj szablonu z kolumnami: Imię, Nazwisko, Data urodzenia.',
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

        $hasName = $headers->contains('imie')
            || $headers->contains('nazwisko')
            || $headers->contains('imie_i_nazwisko')
            || $headers->contains('osoba');

        $hasBirth = $headers->contains('data_urodzenia') || $headers->contains('birth_date');

        return $hasName && $hasBirth;
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

        $nameParts = $this->resolveNameParts($mapped);

        return ParticipantNameMatcher::fullName($nameParts['first_name'], $nameParts['last_name']) === '';
    }

    private function canonicalHeader(string $value): string
    {
        $key = ParticipantNameMatcher::normalizeKey($value);

        return match ($key) {
            'imie', 'first name', 'firstname' => 'imie',
            'nazwisko', 'last name', 'lastname', 'surname' => 'nazwisko',
            'imie i nazwisko', 'imie nazwisko', 'uczestnik', 'osoba', 'name', 'participant' => 'imie_i_nazwisko',
            'data urodzenia', 'data_urodzenia', 'urodzenie', 'birth date', 'birthdate', 'birth_date' => 'data_urodzenia',
            'pesel' => 'pesel',
            'email', 'e mail', 'e-mail' => 'email',
            'telefon', 'phone', 'tel' => 'telefon',
            'nr rezerwacji', 'nr_rezerwacji', 'booking reference', 'booking_reference' => 'nr_rezerwacji',
            default => str_replace(' ', '_', $key),
        };
    }

    /**
     * @param  array<string, string>  $mapped
     * @return array{first_name: ?string, last_name: ?string}
     */
    private function resolveNameParts(array $mapped): array
    {
        $first = trim((string) ($mapped['imie'] ?? ''));
        $last = trim((string) ($mapped['nazwisko'] ?? ''));

        if ($first !== '' || $last !== '') {
            return ['first_name' => $first ?: null, 'last_name' => $last ?: null];
        }

        return ParticipantNameMatcher::parseFullName((string) ($mapped['imie_i_nazwisko'] ?? ''));
    }

    private function parseBirthDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y', 'Y/m/d'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $raw);
                if ($date !== false) {
                    return $date->startOfDay();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizePesel(string $value): ?string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        return strlen($digits) === 11 ? $digits : null;
    }

    /**
     * @param  Collection<int, EventParticipant>  $existing
     */
    private function isDuplicate(Collection $existing, ?string $firstName, ?string $lastName, ?Carbon $birthDate): bool
    {
        $fullName = ParticipantNameMatcher::fullName($firstName, $lastName);

        return $existing->contains(function (EventParticipant $participant) use ($fullName, $birthDate) {
            if (! ParticipantNameMatcher::namesMatch($participant->fullName(), $fullName)) {
                return false;
            }

            if (! $birthDate || ! $participant->birth_date) {
                return true;
            }

            return $participant->birth_date->toDateString() === $birthDate->toDateString();
        });
    }

    /**
     * @return Collection<int, Contract|EventAgreement>
     */
    private function loadAgreements(Event $event): Collection
    {
        return $event->agreements()
            ->get()
            ->filter(function (Contract|EventAgreement $agreement): bool {
                return EventAgreementParticipant::isIndividual($agreement)
                    && ! in_array($agreement->status, ['template', 'cancelled'], true);
            })
            ->values();
    }

    /**
     * @param  Collection<int, Contract|EventAgreement>  $agreements
     */
    private function matchAgreement(Collection $agreements, string $fullName, ?Carbon $birthDate): Contract|EventAgreement|null
    {
        $candidates = $agreements->filter(function (Contract|EventAgreement $agreement) use ($fullName) {
            return ParticipantNameMatcher::namesMatch(EventAgreementParticipant::participantName($agreement), $fullName);
        });

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($birthDate) {
            $withBirth = $candidates->first(function (Contract|EventAgreement $agreement) use ($birthDate) {
                if (! $agreement->participant_birth_date) {
                    return false;
                }

                return $agreement->participant_birth_date->toDateString() === $birthDate->toDateString();
            });

            if ($withBirth) {
                return $withBirth;
            }
        }

        return $candidates->first();
    }
}
