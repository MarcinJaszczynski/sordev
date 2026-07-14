#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Migrates data from SQLite to MySQL/MariaDB for Laravel projects
 * without editing existing app files. It copies only shared columns.
 *
 * Usage:
 *   php scripts/migrate_sqlite_to_mysql.php \
 *     --sqlite=database/database.sqlite \
 *     --mysql-host=127.0.0.1 \
 *     --mysql-port=3306 \
 *     --mysql-db=sor2026_mysql \
 *     --mysql-user=sor2026 \
 *     --mysql-pass='secret'
 */
function arg(string $name, ?string $default = null): ?string
{
    foreach ($GLOBALS['argv'] as $raw) {
        if (str_starts_with($raw, "--{$name}=")) {
            return substr($raw, strlen($name) + 3);
        }
    }

    return $default;
}

function out(string $msg): void
{
    fwrite(STDOUT, $msg.PHP_EOL);
}

function fail(string $msg, int $code = 1): never
{
    fwrite(STDERR, "ERROR: {$msg}".PHP_EOL);
    exit($code);
}

$sqlitePath = arg('sqlite', 'database/database.sqlite');
$mysqlHost = arg('mysql-host', '127.0.0.1');
$mysqlPort = arg('mysql-port', '3306');
$mysqlDb = arg('mysql-db');
$mysqlUser = arg('mysql-user');
$mysqlPass = arg('mysql-pass', '');

if (! $mysqlDb || ! $mysqlUser) {
    fail('Missing required options --mysql-db and --mysql-user');
}

if (! is_file($sqlitePath)) {
    fail("SQLite file not found: {$sqlitePath}");
}

try {
    $sqlite = new PDO('sqlite:'.$sqlitePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $mysqlDsn = "mysql:host={$mysqlHost};port={$mysqlPort};dbname={$mysqlDb};charset=utf8mb4";
    $mysql = new PDO($mysqlDsn, $mysqlUser, $mysqlPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_LOCAL_INFILE => true,
    ]);
} catch (Throwable $e) {
    fail('Connection failed: '.$e->getMessage());
}

out('Connected to SQLite and MySQL.');

$mysql->exec('SET FOREIGN_KEY_CHECKS=0');
$mysql->exec('SET UNIQUE_CHECKS=0');

$tablesStmt = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
$tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

$totalRows = 0;
$processed = 0;
$skipped = [];

foreach ($tables as $table) {
    $processed++;

    $srcColsStmt = $sqlite->query("PRAGMA table_info(\"{$table}\")");
    $srcCols = array_map(static fn (array $r): string => $r['name'], $srcColsStmt->fetchAll());

    $dstColsStmt = $mysql->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION'
    );
    $dstColsStmt->execute(['schema' => $mysqlDb, 'table' => $table]);
    $dstCols = $dstColsStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($dstCols)) {
        $skipped[] = "{$table} (missing destination table)";

        continue;
    }

    $shared = array_values(array_intersect($srcCols, $dstCols));
    if (empty($shared)) {
        $skipped[] = "{$table} (no shared columns)";

        continue;
    }

    $count = (int) $sqlite->query("SELECT COUNT(*) FROM \"{$table}\"")->fetchColumn();
    if ($count === 0) {
        out("[{$processed}/".count($tables)."] {$table}: empty, skipped");

        continue;
    }

    try {
        $mysql->exec("TRUNCATE TABLE `{$table}`");
    } catch (Throwable $e) {
        // In FK-heavy schemas TRUNCATE may be blocked even with FOREIGN_KEY_CHECKS=0.
        $mysql->exec("DELETE FROM `{$table}`");
    }

    $quotedCols = implode(', ', array_map(static fn (string $c): string => "`{$c}`", $shared));
    $placeholders = implode(', ', array_fill(0, count($shared), '?'));
    $insertSql = "INSERT INTO `{$table}` ({$quotedCols}) VALUES ({$placeholders})";
    $insertStmt = $mysql->prepare($insertSql);

    $selectCols = implode(', ', array_map(static fn (string $c): string => '"'.str_replace('"', '""', $c).'"', $shared));
    $srcRowsStmt = $sqlite->query("SELECT {$selectCols} FROM \"{$table}\"");

    $inserted = 0;
    $mysql->beginTransaction();
    while ($row = $srcRowsStmt->fetch(PDO::FETCH_ASSOC)) {
        if (
            in_array($table, ['event_template_program_points', 'event_template_program_point_parent'], true)
            && array_key_exists('order', $row)
            && ($row['order'] === null || $row['order'] === '')
        ) {
            $row['order'] = 1;
        }

        try {
            $insertStmt->execute(array_values($row));
            $inserted++;
        } catch (Throwable $e) {
            out("[ERROR] {$table} row: ".json_encode($row));
            out('[ERROR] Exception: '.$e->getMessage());
            // Możesz dodać break; jeśli chcesz przerwać na pierwszym błędzie
        }
    }
    $mysql->commit();

    $totalRows += $inserted;
    out("[{$processed}/".count($tables)."] {$table}: copied {$inserted} row(s)");
}

$mysql->exec('SET UNIQUE_CHECKS=1');
$mysql->exec('SET FOREIGN_KEY_CHECKS=1');

out('Migration finished.');
out('Total copied rows: '.$totalRows);

if (! empty($skipped)) {
    out('Skipped tables:');
    foreach ($skipped as $skip) {
        out(' - '.$skip);
    }
}
