<?php

declare(strict_types=1);

const DUMP_FILE = __DIR__.'/../pliki/host803729_bazasor.sql';

const TABLES = [
    'events',
    'event_elements',
    'event_contractors',
    'event_payments',
    'notes',
];

function normalizeNullableDateTime(mixed $value): ?string
{
    if (! is_string($value) || trim($value) === '') {
        return null;
    }

    $trimmed = trim($value);

    if ($trimmed === '1970-01-01 01:00:00' || $trimmed === '0000-00-00 00:00:00') {
        return null;
    }

    return $trimmed;
}

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

function parseInsertStatement(string $statement): array
{
    if (! preg_match('/^INSERT INTO `(?P<table>[^`]+)` \((?P<columns>[^)]+)\) VALUES\s*(?P<values>.*);$/s', trim($statement), $matches)) {
        throw new RuntimeException('Nie udało się sparsować INSERT statement.');
    }

    $columns = array_map(
        static fn (string $col): string => trim($col, " `\t\n\r\0\x0B"),
        explode(',', $matches['columns'])
    );

    return [
        'table' => $matches['table'],
        'columns' => $columns,
        'rows' => parseValuesTuples($matches['values']),
    ];
}

function parseValuesTuples(string $valuesChunk): array
{
    $rows = [];
    $length = strlen($valuesChunk);

    $inString = false;
    $escaped = false;
    $depth = 0;
    $buffer = '';

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

function parseRowValues(string $row): array
{
    $values = [];
    $length = strlen($row);

    $inString = false;
    $escaped = false;
    $buffer = '';

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

    if ($buffer !== '' || str_ends_with($row, ',')) {
        $values[] = decodeSqlValue($buffer);
    }

    return $values;
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
        if (str_contains($value, '.') || str_contains(strtolower($value), 'e')) {
            return (float) $value;
        }

        return (int) $value;
    }

    return $value;
}

function parseDumpTables(string $dumpFile, array $tableNames): array
{
    $handle = fopen($dumpFile, 'rb');

    if (! $handle) {
        throw new RuntimeException('Nie można otworzyć dumpa: '.$dumpFile);
    }

    $targets = array_fill_keys($tableNames, []);
    $currentSql = '';
    $currentTable = null;

    while (($line = fgets($handle)) !== false) {
        if ($currentTable === null) {
            foreach ($tableNames as $tableName) {
                if (str_starts_with($line, "INSERT INTO `{$tableName}`")) {
                    $currentTable = $tableName;
                    $currentSql = $line;

                    if (statementIsComplete($currentSql)) {
                        $parsed = parseInsertStatement($currentSql);
                        $targets[$parsed['table']] = array_merge($targets[$parsed['table']], $parsed['rows']);
                        $currentSql = '';
                        $currentTable = null;
                    }

                    break;
                }
            }

            continue;
        }

        if ($currentSql !== '' && $currentSql !== $line) {
            $currentSql .= $line;
        }

        if (statementIsComplete($currentSql)) {
            $parsed = parseInsertStatement($currentSql);
            $targets[$parsed['table']] = array_merge($targets[$parsed['table']], $parsed['rows']);
            $currentSql = '';
            $currentTable = null;
        }
    }

    fclose($handle);

    return $targets;
}

function associateRows(array $columns, array $rows): array
{
    return array_map(static fn (array $row): array => array_combine($columns, $row), $rows);
}

function rowsByKey(array $rows, string $key): array
{
    $grouped = [];

    foreach ($rows as $row) {
        $groupKey = $row[$key] ?? null;

        if ($groupKey === null) {
            continue;
        }

        $grouped[(string) $groupKey][] = $row;
    }

    return $grouped;
}

if (! file_exists(DUMP_FILE)) {
    throw new RuntimeException('Brak pliku dumpa: '.DUMP_FILE);
}

$db = new PDO('mysql:host=127.0.0.1;dbname=sor2026_mysql;charset=utf8mb4', 'sor2026', '5451', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "Parsowanie dumpa...\n";

$parsedRows = parseDumpTables(DUMP_FILE, TABLES);

// Dla tej migracji kolumny są stałe i znane ze struktury dumpa.
$eventColumns = [
    'id', 'eventName', 'eventOfficeId', 'eventNote', 'created_at', 'updated_at', 'eventStartDateTime', 'eventEndDateTime',
    'eventStartDescription', 'eventEndDescription', 'eventDietAlert', 'eventTotalQty', 'eventGuardiansQty', 'eventFreeQty',
    'eventStatus', 'eventPurchaserName', 'eventPurchaserStreet', 'eventPurchaserCity', 'eventPurchaserNip',
    'eventPurchaserContactPerson', 'eventPurchaserTel', 'eventPurchaserEmail', 'eventPilot', 'eventDriver',
    'eventAdvancePayment', 'eventPilotNotes', 'busBoardTime', 'duration', 'todo_id', 'purchaser_id', 'author_id',
    'statusChangeDatetime', 'orderNote',
];

$eventElementsColumns = [
    'id', 'element_name', 'eventElementDescription', 'eventIdinEventElements', 'eventElementPilotPrint',
    'eventElementHotelPrint', 'created_at', 'updated_at', 'eventElementStart', 'eventElementEnd', 'eventElementCost',
    'eventElementCostStatus', 'eventElementCostPayer', 'eventElementNote', 'eventElementCostNote', 'eventElementContact',
    'eventElementReservation', 'eventElementInvoiceNo', 'last_change_user_id', 'booking', 'contractor_id', 'active', 'desc',
];

$eventContractorsColumns = ['id', 'event_id', 'eventelement_id', 'contractor_id', 'contractortype_id', 'created_at', 'updated_at', 'desc'];

$eventPaymentsColumns = [
    'id', 'paymentName', 'paymentDescription', 'event_id', 'payer', 'paymentStatus', 'invoice', 'paymentNote',
    'paymentDate', 'qty', 'plannedQty', 'price', 'plannedPrice', 'currency_id', 'planned_currency_id', 'exchange_rate',
    'planned_exchange_rate', 'element_id', 'contractor_id', 'paymenttype_id', 'contractortype_id', 'accepted', 'desc',
];

$notesColumns = ['id', 'created_at', 'updated_at', 'name', 'description', 'author_id', 'contractor_id', 'event_id', 'todo_id', 'event_element_id', 'note_id'];

$events = associateRows($eventColumns, $parsedRows['events'] ?? []);
$eventElements = associateRows($eventElementsColumns, $parsedRows['event_elements'] ?? []);
$eventContractors = associateRows($eventContractorsColumns, $parsedRows['event_contractors'] ?? []);
$eventPayments = associateRows($eventPaymentsColumns, $parsedRows['event_payments'] ?? []);
$eventNotes = associateRows($notesColumns, $parsedRows['notes'] ?? []);

$elementsByEvent = rowsByKey($eventElements, 'eventIdinEventElements');
$contractorsByEvent = rowsByKey($eventContractors, 'event_id');
$paymentsByEvent = rowsByKey($eventPayments, 'event_id');
$notesByEvent = rowsByKey($eventNotes, 'event_id');

$contractorMap = [];
foreach ($db->query('SELECT id, name FROM contractors WHERE name IS NOT NULL AND name <> ""') as $row) {
    $contractorMap[mb_strtolower(trim((string) $row['name']))] = (int) $row['id'];
}

$insertSql = <<<'SQL'
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
    :legacy_purchaser_id, :contractor_id,
    :elements_json, :contractors_json, :payments_json, :notes_json,
    NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
    legacy_id = legacy_id
SQL;

$insert = $db->prepare($insertSql);

$db->beginTransaction();

$imported = 0;
$skipped = 0;

echo 'Import events: '.count($events)."\n";

foreach ($events as $event) {
    $legacyId = (int) ($event['id'] ?? 0);

    if ($legacyId <= 0) {
        $skipped++;

        continue;
    }

    $clientName = isset($event['eventPurchaserName']) ? trim((string) $event['eventPurchaserName']) : null;
    $contractorId = $clientName ? ($contractorMap[mb_strtolower($clientName)] ?? null) : null;

    $insert->execute([
        'legacy_id' => $legacyId,
        'office_id' => $event['eventOfficeId'] ?? null,
        'name' => $event['eventName'] ?? 'Brak nazwy',
        'legacy_status' => $event['eventStatus'] ?? null,
        'start_datetime' => normalizeNullableDateTime($event['eventStartDateTime'] ?? null),
        'end_datetime' => normalizeNullableDateTime($event['eventEndDateTime'] ?? null),
        'duration_days' => $event['duration'] ?? null,
        'participant_count' => $event['eventTotalQty'] ?? null,
        'guardians_count' => $event['eventGuardiansQty'] ?? null,
        'free_count' => $event['eventFreeQty'] ?? null,
        'client_name' => $event['eventPurchaserName'] ?? null,
        'client_street' => $event['eventPurchaserStreet'] ?? null,
        'client_city' => $event['eventPurchaserCity'] ?? null,
        'client_nip' => $event['eventPurchaserNip'] ?? null,
        'client_contact_person' => $event['eventPurchaserContactPerson'] ?? null,
        'client_phone' => $event['eventPurchaserTel'] ?? null,
        'client_email' => $event['eventPurchaserEmail'] ?? null,
        'pilot' => $event['eventPilot'] ?? null,
        'driver' => $event['eventDriver'] ?? null,
        'bus_board_time' => normalizeNullableDateTime($event['busBoardTime'] ?? null),
        'advance_payment' => $event['eventAdvancePayment'] ?? null,
        'notes' => $event['eventNote'] ?? null,
        'start_description' => $event['eventStartDescription'] ?? null,
        'end_description' => $event['eventEndDescription'] ?? null,
        'diet_alert' => $event['eventDietAlert'] ?? null,
        'pilot_notes' => $event['eventPilotNotes'] ?? null,
        'order_note' => $event['orderNote'] ?? null,
        'legacy_purchaser_id' => $event['purchaser_id'] ?? null,
        'contractor_id' => $contractorId,
        'elements_json' => json_encode($elementsByEvent[(string) $legacyId] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'contractors_json' => json_encode($contractorsByEvent[(string) $legacyId] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'payments_json' => json_encode($paymentsByEvent[(string) $legacyId] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'notes_json' => json_encode($notesByEvent[(string) $legacyId] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $imported++;
}

$db->commit();

echo "Import zakończony.\n";
echo "Zaimportowano: {$imported}\n";
echo "Pominięto: {$skipped}\n";
