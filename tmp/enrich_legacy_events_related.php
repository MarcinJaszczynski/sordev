<?php

declare(strict_types=1);

const DUMP_FILE = __DIR__.'/../pliki/host803729_bazasor.sql';
const TABLES = ['event_elements', 'event_contractors', 'event_payments', 'notes'];

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

function parseDumpTables(string $dumpFile, array $tableNames): array
{
    $handle = fopen($dumpFile, 'rb');

    if (! $handle) {
        throw new RuntimeException('Nie można otworzyć dumpa: '.$dumpFile);
    }

    $targets = [];
    foreach ($tableNames as $tableName) {
        $targets[$tableName] = [
            'columns' => [],
            'rows' => [],
        ];
    }
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
                        if ($targets[$parsed['table']]['columns'] === []) {
                            $targets[$parsed['table']]['columns'] = $parsed['columns'];
                        }
                        $targets[$parsed['table']]['rows'] = array_merge($targets[$parsed['table']]['rows'], $parsed['rows']);
                        $currentSql = '';
                        $currentTable = null;
                    }

                    break;
                }
            }

            continue;
        }

        $currentSql .= $line;

        if (statementIsComplete($currentSql)) {
            $parsed = parseInsertStatement($currentSql);
            if ($targets[$parsed['table']]['columns'] === []) {
                $targets[$parsed['table']]['columns'] = $parsed['columns'];
            }
            $targets[$parsed['table']]['rows'] = array_merge($targets[$parsed['table']]['rows'], $parsed['rows']);
            $currentSql = '';
            $currentTable = null;
        }
    }

    fclose($handle);

    return $targets;
}

function associateRows(array $columns, array $rows): array
{
    $mapped = [];

    foreach ($rows as $row) {
        if (count($row) !== count($columns)) {
            continue;
        }

        $combined = array_combine($columns, $row);

        if ($combined !== false) {
            $mapped[] = $combined;
        }
    }

    return $mapped;
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

echo "Parsowanie tabel powiązanych...\n";
$parsedRows = parseDumpTables(DUMP_FILE, TABLES);

$elements = associateRows($parsedRows['event_elements']['columns'] ?? [], $parsedRows['event_elements']['rows'] ?? []);
$contractors = associateRows($parsedRows['event_contractors']['columns'] ?? [], $parsedRows['event_contractors']['rows'] ?? []);
$payments = associateRows($parsedRows['event_payments']['columns'] ?? [], $parsedRows['event_payments']['rows'] ?? []);
$notes = associateRows($parsedRows['notes']['columns'] ?? [], $parsedRows['notes']['rows'] ?? []);

$elementsByEvent = rowsByKey($elements, 'eventIdinEventElements');
$contractorsByEvent = rowsByKey($contractors, 'event_id');
$paymentsByEvent = rowsByKey($payments, 'event_id');
$notesByEvent = rowsByKey($notes, 'event_id');

$allEventIds = array_unique(array_merge(
    array_keys($elementsByEvent),
    array_keys($contractorsByEvent),
    array_keys($paymentsByEvent),
    array_keys($notesByEvent)
));

$update = $db->prepare(
    'UPDATE legacy_events SET elements_json = :elements_json, contractors_json = :contractors_json, payments_json = :payments_json, notes_json = :notes_json, updated_at = NOW() WHERE legacy_id = :legacy_id'
);

$db->beginTransaction();

$updated = 0;
$skipped = 0;

foreach ($allEventIds as $legacyId) {
    $legacyIdInt = (int) $legacyId;

    if ($legacyIdInt <= 0) {
        $skipped++;

        continue;
    }

    $update->execute([
        'legacy_id' => $legacyIdInt,
        'elements_json' => json_encode($elementsByEvent[(string) $legacyIdInt] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'contractors_json' => json_encode($contractorsByEvent[(string) $legacyIdInt] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'payments_json' => json_encode($paymentsByEvent[(string) $legacyIdInt] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'notes_json' => json_encode($notesByEvent[(string) $legacyIdInt] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    if ($update->rowCount() > 0) {
        $updated++;
    } else {
        $skipped++;
    }
}

$db->commit();

echo "Enrichment zakończony.\n";
echo "Zaktualizowano: {$updated}\n";
echo "Pominięto: {$skipped}\n";
