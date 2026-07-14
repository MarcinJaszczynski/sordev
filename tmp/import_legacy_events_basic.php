<?php

declare(strict_types=1);

const DUMP_FILE = __DIR__.'/../pliki/host803729_bazasor.sql';

function statementIsComplete(string $sql): bool
{
    $inString = false;
    $escaped = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $ch = $sql[$i];

        if ($escaped) {
            $escaped = false;

            continue;
        }

        if ($ch === '\\') {
            $escaped = true;

            continue;
        }

        if ($ch === "'") {
            $inString = ! $inString;

            continue;
        }

        if (! $inString && $ch === ';') {
            return true;
        }
    }

    return false;
}

function decodeSqlValue(string $raw): mixed
{
    $value = trim($raw);

    if (strcasecmp($value, 'NULL') === 0) {
        return null;
    }

    if ($value === '') {
        return '';
    }

    if (preg_match('/^\'(.*)\'$/s', $value, $m)) {
        return stripcslashes($m[1]);
    }

    if (is_numeric($value)) {
        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    return $value;
}

function parseRowValues(string $row): array
{
    $values = [];
    $buffer = '';
    $inString = false;
    $escaped = false;
    $length = strlen($row);

    for ($i = 0; $i < $length; $i++) {
        $ch = $row[$i];

        if ($escaped) {
            $buffer .= $ch;
            $escaped = false;

            continue;
        }

        if ($ch === '\\') {
            $buffer .= $ch;
            $escaped = true;

            continue;
        }

        if ($ch === "'") {
            $buffer .= $ch;
            $inString = ! $inString;

            continue;
        }

        if (! $inString && $ch === ',') {
            $values[] = decodeSqlValue($buffer);
            $buffer = '';

            continue;
        }

        $buffer .= $ch;
    }

    if ($buffer !== '') {
        $values[] = decodeSqlValue($buffer);
    }

    return $values;
}

function parseValuesTuples(string $valuesChunk): array
{
    $rows = [];
    $inString = false;
    $escaped = false;
    $depth = 0;
    $buffer = '';
    $length = strlen($valuesChunk);

    for ($i = 0; $i < $length; $i++) {
        $ch = $valuesChunk[$i];

        if ($escaped) {
            $buffer .= $ch;
            $escaped = false;

            continue;
        }

        if ($ch === '\\') {
            $buffer .= $ch;
            $escaped = true;

            continue;
        }

        if ($ch === "'") {
            $buffer .= $ch;
            $inString = ! $inString;

            continue;
        }

        if (! $inString && $ch === '(') {
            if ($depth > 0) {
                $buffer .= $ch;
            }
            $depth++;

            continue;
        }

        if (! $inString && $ch === ')') {
            $depth--;
            if ($depth === 0) {
                $rows[] = parseRowValues($buffer);
                $buffer = '';

                continue;
            }
            $buffer .= $ch;

            continue;
        }

        if ($depth > 0) {
            $buffer .= $ch;
        }
    }

    return $rows;
}

function parseEventsRows(string $statement): array
{
    if (! preg_match('/^INSERT INTO `events` \((?P<columns>[^)]+)\) VALUES\s*(?P<values>.*);$/s', trim($statement), $matches)) {
        return [];
    }

    $columns = array_map(
        static fn (string $col): string => trim($col, " `\t\n\r\0\x0B"),
        explode(',', $matches['columns'])
    );

    $rows = parseValuesTuples($matches['values']);

    return array_map(static fn (array $row): array => array_combine($columns, $row), $rows);
}

function normalizeDate(?string $value): ?string
{
    if (! $value) {
        return null;
    }

    $trimmed = trim($value);
    if ($trimmed === '' || $trimmed === '1970-01-01 01:00:00' || $trimmed === '0000-00-00 00:00:00') {
        return null;
    }

    if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $trimmed)) {
        return null;
    }

    try {
        $dt = new DateTimeImmutable($trimmed);
    } catch (Throwable) {
        return null;
    }

    // Kolumny są typu timestamp, więc odrzucamy daty spoza sensownego zakresu.
    $year = (int) $dt->format('Y');
    if ($year < 1970 || $year > 2100) {
        return null;
    }

    return $trimmed;
}

if (! file_exists(DUMP_FILE)) {
    fwrite(STDERR, 'Brak dumpa: '.DUMP_FILE."\n");
    exit(1);
}

$pdo = new PDO('mysql:host=127.0.0.1;dbname=sor2026_mysql;charset=utf8mb4', 'sor2026', '5451', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$insert = $pdo->prepare(<<<'SQL'
INSERT INTO legacy_events (
    legacy_id, office_id, name, legacy_status,
    start_datetime, end_datetime, duration_days,
    participant_count, guardians_count, free_count,
    client_name, client_street, client_city, client_nip, client_contact_person, client_phone, client_email,
    pilot, driver, bus_board_time, advance_payment,
    notes, start_description, end_description, diet_alert, pilot_notes, order_note,
    legacy_purchaser_id, contractor_id,
    elements_json, contractors_json, payments_json, notes_json,
    created_at, updated_at
) VALUES (
    :legacy_id, :office_id, :name, :legacy_status,
    :start_datetime, :end_datetime, :duration_days,
    :participant_count, :guardians_count, :free_count,
    :client_name, :client_street, :client_city, :client_nip, :client_contact_person, :client_phone, :client_email,
    :pilot, :driver, :bus_board_time, :advance_payment,
    :notes, :start_description, :end_description, :diet_alert, :pilot_notes, :order_note,
    :legacy_purchaser_id, NULL,
    '[]', '[]', '[]', '[]',
    NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
    office_id = VALUES(office_id),
    name = VALUES(name),
    legacy_status = VALUES(legacy_status),
    start_datetime = VALUES(start_datetime),
    end_datetime = VALUES(end_datetime),
    duration_days = VALUES(duration_days),
    participant_count = VALUES(participant_count),
    guardians_count = VALUES(guardians_count),
    free_count = VALUES(free_count),
    client_name = VALUES(client_name),
    client_street = VALUES(client_street),
    client_city = VALUES(client_city),
    client_nip = VALUES(client_nip),
    client_contact_person = VALUES(client_contact_person),
    client_phone = VALUES(client_phone),
    client_email = VALUES(client_email),
    pilot = VALUES(pilot),
    driver = VALUES(driver),
    bus_board_time = VALUES(bus_board_time),
    advance_payment = VALUES(advance_payment),
    notes = VALUES(notes),
    start_description = VALUES(start_description),
    end_description = VALUES(end_description),
    diet_alert = VALUES(diet_alert),
    pilot_notes = VALUES(pilot_notes),
    order_note = VALUES(order_note),
    legacy_purchaser_id = VALUES(legacy_purchaser_id),
    updated_at = NOW()
SQL);

$pdo->beginTransaction();

$h = fopen(DUMP_FILE, 'rb');
$collecting = false;
$sql = '';
$count = 0;

while (($line = fgets($h)) !== false) {
    if (! $collecting) {
        if (str_starts_with($line, 'INSERT INTO `events`')) {
            $collecting = true;
            $sql = $line;

            if (statementIsComplete($sql)) {
                $rows = parseEventsRows($sql);
                foreach ($rows as $row) {
                    $insert->execute([
                        'legacy_id' => (int) $row['id'],
                        'office_id' => $row['eventOfficeId'] ?? null,
                        'name' => $row['eventName'] ?? 'Brak nazwy',
                        'legacy_status' => $row['eventStatus'] ?? null,
                        'start_datetime' => normalizeDate((string) ($row['eventStartDateTime'] ?? '')),
                        'end_datetime' => normalizeDate((string) ($row['eventEndDateTime'] ?? '')),
                        'duration_days' => $row['duration'] ?? null,
                        'participant_count' => $row['eventTotalQty'] ?? null,
                        'guardians_count' => $row['eventGuardiansQty'] ?? null,
                        'free_count' => $row['eventFreeQty'] ?? null,
                        'client_name' => $row['eventPurchaserName'] ?? null,
                        'client_street' => $row['eventPurchaserStreet'] ?? null,
                        'client_city' => $row['eventPurchaserCity'] ?? null,
                        'client_nip' => $row['eventPurchaserNip'] ?? null,
                        'client_contact_person' => $row['eventPurchaserContactPerson'] ?? null,
                        'client_phone' => $row['eventPurchaserTel'] ?? null,
                        'client_email' => $row['eventPurchaserEmail'] ?? null,
                        'pilot' => $row['eventPilot'] ?? null,
                        'driver' => $row['eventDriver'] ?? null,
                        'bus_board_time' => normalizeDate((string) ($row['busBoardTime'] ?? '')),
                        'advance_payment' => $row['eventAdvancePayment'] ?? null,
                        'notes' => $row['eventNote'] ?? null,
                        'start_description' => $row['eventStartDescription'] ?? null,
                        'end_description' => $row['eventEndDescription'] ?? null,
                        'diet_alert' => $row['eventDietAlert'] ?? null,
                        'pilot_notes' => $row['eventPilotNotes'] ?? null,
                        'order_note' => $row['orderNote'] ?? null,
                        'legacy_purchaser_id' => $row['purchaser_id'] ?? null,
                    ]);
                    $count++;
                }

                $collecting = false;
                $sql = '';
            }
        }

        continue;
    }

    $sql .= $line;

    if (statementIsComplete($sql)) {
        $rows = parseEventsRows($sql);

        foreach ($rows as $row) {
            $insert->execute([
                'legacy_id' => (int) $row['id'],
                'office_id' => $row['eventOfficeId'] ?? null,
                'name' => $row['eventName'] ?? 'Brak nazwy',
                'legacy_status' => $row['eventStatus'] ?? null,
                'start_datetime' => normalizeDate((string) ($row['eventStartDateTime'] ?? '')),
                'end_datetime' => normalizeDate((string) ($row['eventEndDateTime'] ?? '')),
                'duration_days' => $row['duration'] ?? null,
                'participant_count' => $row['eventTotalQty'] ?? null,
                'guardians_count' => $row['eventGuardiansQty'] ?? null,
                'free_count' => $row['eventFreeQty'] ?? null,
                'client_name' => $row['eventPurchaserName'] ?? null,
                'client_street' => $row['eventPurchaserStreet'] ?? null,
                'client_city' => $row['eventPurchaserCity'] ?? null,
                'client_nip' => $row['eventPurchaserNip'] ?? null,
                'client_contact_person' => $row['eventPurchaserContactPerson'] ?? null,
                'client_phone' => $row['eventPurchaserTel'] ?? null,
                'client_email' => $row['eventPurchaserEmail'] ?? null,
                'pilot' => $row['eventPilot'] ?? null,
                'driver' => $row['eventDriver'] ?? null,
                'bus_board_time' => normalizeDate((string) ($row['busBoardTime'] ?? '')),
                'advance_payment' => $row['eventAdvancePayment'] ?? null,
                'notes' => $row['eventNote'] ?? null,
                'start_description' => $row['eventStartDescription'] ?? null,
                'end_description' => $row['eventEndDescription'] ?? null,
                'diet_alert' => $row['eventDietAlert'] ?? null,
                'pilot_notes' => $row['eventPilotNotes'] ?? null,
                'order_note' => $row['orderNote'] ?? null,
                'legacy_purchaser_id' => $row['purchaser_id'] ?? null,
            ]);
            $count++;
        }

        $collecting = false;
        $sql = '';
    }
}

fclose($h);
$pdo->commit();

echo "Zaimportowano eventy: {$count}\n";
