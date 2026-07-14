<?php

declare(strict_types=1);

/**
 * Merge data from src MySQL database into dst MySQL database, keeping dst schema unchanged.
 *
 * Usage:
 *   php scripts/merge_mysql_databases.php <src_db> <dst_db>
 *
 * Reads connection params from .env:
 *   DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD
 *
 * Strategy per table:
 * - Only tables present in both src and dst are considered
 * - Only intersection of columns (by name) is copied
 * - If dst has a single-column PK that exists in common columns: copy only missing PKs
 * - Otherwise: INSERT IGNORE (best-effort) for common columns
 *
 * Writes report to: database/merge_reports/mysql_merge_<timestamp>.json
 */
if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/merge_mysql_databases.php <src_db> <dst_db>\n");
    exit(2);
}

$root = realpath(__DIR__.'/..');
if ($root === false) {
    fwrite(STDERR, "Cannot resolve project root.\n");
    exit(3);
}

$envPath = $root.'/.env';
if (! is_file($envPath)) {
    fwrite(STDERR, "Missing .env at: {$envPath}\n");
    exit(4);
}

function env_read(string $path, string $key): ?string
{
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return null;
    }
    foreach ($lines as $line) {
        if (str_starts_with($line, '#')) {
            continue;
        }
        if (! str_starts_with($line, $key.'=')) {
            continue;
        }
        $value = substr($line, strlen($key) + 1);
        $value = rtrim($value, "\r\n");
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }

    return null;
}

$srcDb = (string) $argv[1];
$dstDb = (string) $argv[2];

$host = env_read($envPath, 'DB_HOST') ?: '127.0.0.1';
$port = env_read($envPath, 'DB_PORT') ?: '3306';
$user = env_read($envPath, 'DB_USERNAME') ?: 'sor';
$pass = env_read($envPath, 'DB_PASSWORD') ?: 'sor_secret';

$dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: {$e->getMessage()}\n");
    exit(5);
}

function q(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function exec_stmt(PDO $pdo, string $sql): void
{
    $pdo->exec($sql);
}

function ident(string $name): string
{
    return '`'.str_replace('`', '``', $name).'`';
}

$report = [
    'src' => $srcDb,
    'dst' => $dstDb,
    'started_at' => date('c'),
    'tables' => [],
];

$tablesSrc = q($pdo, "SELECT table_name FROM information_schema.tables WHERE table_schema = :db AND table_type = 'BASE TABLE' ORDER BY table_name", [':db' => $srcDb]);
$tablesDst = q($pdo, "SELECT table_name FROM information_schema.tables WHERE table_schema = :db AND table_type = 'BASE TABLE' ORDER BY table_name", [':db' => $dstDb]);
$dstSet = [];
foreach ($tablesDst as $r) {
    $dstSet[$r['table_name']] = true;
}

exec_stmt($pdo, 'SET SESSION foreign_key_checks = 0');
exec_stmt($pdo, "SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");

foreach ($tablesSrc as $row) {
    $table = $row['table_name'];
    if (! isset($dstSet[$table])) {
        $report['tables'][$table] = ['status' => 'no_table_in_dst'];

        continue;
    }

    $colsSrc = q($pdo, 'SELECT column_name FROM information_schema.columns WHERE table_schema = :db AND table_name = :t ORDER BY ordinal_position', [
        ':db' => $srcDb,
        ':t' => $table,
    ]);
    $colsDst = q($pdo, 'SELECT column_name FROM information_schema.columns WHERE table_schema = :db AND table_name = :t ORDER BY ordinal_position', [
        ':db' => $dstDb,
        ':t' => $table,
    ]);

    $srcCols = array_map(fn ($r) => $r['column_name'], $colsSrc);
    $dstCols = array_map(fn ($r) => $r['column_name'], $colsDst);
    $common = array_values(array_intersect($dstCols, $srcCols));

    if ($common === []) {
        $report['tables'][$table] = ['status' => 'no_common_columns'];

        continue;
    }

    $pkCols = q($pdo, "
        SELECT kcu.column_name
        FROM information_schema.table_constraints tc
        JOIN information_schema.key_column_usage kcu
          ON kcu.constraint_name = tc.constraint_name
         AND kcu.table_schema = tc.table_schema
         AND kcu.table_name = tc.table_name
        WHERE tc.constraint_type = 'PRIMARY KEY'
          AND tc.table_schema = :db
          AND tc.table_name = :t
        ORDER BY kcu.ordinal_position
    ", [':db' => $dstDb, ':t' => $table]);
    $pk = array_map(fn ($r) => $r['column_name'], $pkCols);

    $colsSql = implode(', ', array_map(fn ($c) => ident($c), $common));
    $srcTableSql = ident($srcDb).'.'.ident($table);
    $dstTableSql = ident($dstDb).'.'.ident($table);

    $tableReport = [
        'status' => 'pending',
        'common_columns' => count($common),
        'pk' => $pk,
        'copied_rows' => 0,
        'mode' => null,
        'error' => null,
    ];

    try {
        if (count($pk) === 1 && in_array($pk[0], $common, true)) {
            $pkCol = ident($pk[0]);
            $tableReport['mode'] = 'missing_by_single_pk';
            $sql = "
                INSERT INTO {$dstTableSql} ({$colsSql})
                SELECT {$colsSql}
                FROM {$srcTableSql} s
                LEFT JOIN {$dstTableSql} d ON d.{$pkCol} = s.{$pkCol}
                WHERE d.{$pkCol} IS NULL
            ";
            $affected = $pdo->exec($sql);
            $tableReport['copied_rows'] = (int) ($affected === false ? 0 : $affected);
            $tableReport['status'] = 'done';
        } else {
            $tableReport['mode'] = 'insert_ignore_common_cols';
            $sql = "
                INSERT IGNORE INTO {$dstTableSql} ({$colsSql})
                SELECT {$colsSql}
                FROM {$srcTableSql}
            ";
            $affected = $pdo->exec($sql);
            $tableReport['copied_rows'] = (int) ($affected === false ? 0 : $affected);
            $tableReport['status'] = 'done';
        }
    } catch (Throwable $e) {
        $tableReport['status'] = 'error';
        $tableReport['error'] = $e->getMessage();
    }

    $report['tables'][$table] = $tableReport;
}

exec_stmt($pdo, 'SET SESSION foreign_key_checks = 1');

$report['finished_at'] = date('c');

$reportDir = $root.'/database/merge_reports';
if (! is_dir($reportDir)) {
    @mkdir($reportDir, 0775, true);
}

$reportFile = $reportDir.'/mysql_merge_'.date('Ymd_His').'.json';
file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Done. Report: {$reportFile}\n";
