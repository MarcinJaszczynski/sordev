<?php
// merge_sqlite_full.php
// Bezpiecznie wstawia brakujące wiersze i tworzy brakujące tabele z src -> dst,
// zachowuje strukturę dest (jeśli dest ma dodatkowe kolumny używa ich defaultów lub NULL),
// nie nadpisuje istniejących rekordów (INSERT OR IGNORE / checks by PK).

if ($argc < 3) {
    echo "Usage: php merge_sqlite_full.php <src_db> <dst_db>\n";
    exit(2);
}
$src = $argv[1];
$dst = $argv[2];
if (!file_exists($src)) { echo "Source not found: $src\n"; exit(3); }
if (!file_exists($dst)) { echo "Destination not found: $dst\n"; exit(4); }

$backup = $dst . '.bak.' . date('Ymd_His');
if (!@copy($dst, $backup)) {
    echo "Failed to create backup: $backup\n";
    exit(5);
}
echo "Backup created: $backup\n";

try {
    $db = new PDO('sqlite:' . $dst);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Attach source DB
    $db->exec("ATTACH DATABASE '$src' AS src");
    $db->exec("PRAGMA foreign_keys = OFF");

    // Get tables from source
    $tables = $db->query("SELECT name, sql FROM src.sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_ASSOC);
    $tablesDst = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);

    $report = [];

    foreach ($tables as $trow) {
        $table = $trow['name'];
        echo "Processing table: $table\n";
        $existsInDst = in_array($table, $tablesDst);

        if (!$existsInDst) {
            // Create table in dst using src SQL
            $createSql = $trow['sql'];
            if ($createSql) {
                echo " - creating table in dest: $table\n";
                $db->exec($createSql);
            } else {
                echo " - no create SQL for $table, skipping creation\n";
                $report[$table] = ['status' => 'no_create_sql'];
                continue;
            }
            // copy all rows
            $colsSrcInfo = $db->query("PRAGMA src.table_info('$table')")->fetchAll(PDO::FETCH_ASSOC);
            $colsSrc = array_map(fn($r) => $r['name'], $colsSrcInfo);
            $colList = implode(', ', array_map(fn($c) => '"'.$c.'"', $colsSrc));
            $sql = "INSERT INTO \"$table\" ($colList) SELECT $colList FROM src.\"$table\";";
            $db->beginTransaction();
            $db->exec($sql);
            $db->commit();
            $count = $db->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
            $report[$table] = ['status' => 'created_and_copied', 'rows' => (int)$count];
            echo " - copied rows: $count\n";
            continue;
        }

        // Table exists in destination — insert missing rows only.
        // Get column lists and defaults from destination
        $colsDstInfo = $db->query("PRAGMA table_info('$table')")->fetchAll(PDO::FETCH_ASSOC);
        $colsDst = [];
        $pkCols = [];
        foreach ($colsDstInfo as $c) {
            $colsDst[] = $c['name'];
            if ((int)$c['pk'] > 0) $pkCols[] = $c['name'];
        }

        // Get columns available in source
        $colsSrcInfo = $db->query("PRAGMA src.table_info('$table')")->fetchAll(PDO::FETCH_ASSOC);
        $colsSrc = array_map(fn($r) => $r['name'], $colsSrcInfo);

        // For dest columns that don't exist in src, get default expressions if any
        $colExprs = [];
        foreach ($colsDstInfo as $c) {
            $name = $c['name'];
            if (in_array($name, $colsSrc)) {
                $colExprs[] = "src." . '"' . $name . '"';
            } else {
                // Use default if present, otherwise NULL
                $d = $c['dflt_value'];
                if ($d !== null) {
                    $colExprs[] = $d . " AS " . '"' . $name . '"';
                } else {
                    $colExprs[] = "NULL AS " . '"' . $name . '"';
                }
            }
        }

        $colListQuoted = implode(', ', array_map(fn($c) => '"'.$c.'"', $colsDst));
        $selectExpr = implode(', ', $colExprs);

        // We will insert rows from src that don't exist in dst based on PK if available, otherwise use INSERT OR IGNORE
        if (!empty($pkCols) && count($pkCols) == 1) {
            $pk = $pkCols[0];
            echo " - single PK column detected: $pk\n";
            // Insert rows where dst.pk IS NULL (i.e., id not present)
            $sql = "INSERT OR IGNORE INTO \"$table\" ($colListQuoted) SELECT $selectExpr FROM src.\"$table\" WHERE src.\"$pk\" IS NOT NULL AND NOT EXISTS (SELECT 1 FROM \"$table\" d WHERE d.\"$pk\" = src.\"$pk\");";
        } else {
            // No simple PK — use INSERT OR IGNORE (may rely on unique constraints)
            echo " - no single PK detected, using INSERT OR IGNORE\n";
            $sql = "INSERT OR IGNORE INTO \"$table\" ($colListQuoted) SELECT $selectExpr FROM src.\"$table\";";
        }

        $db->beginTransaction();
        try {
            $db->exec($sql);
            $db->commit();
            $rowsAfter = $db->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
            $report[$table] = ['status' => 'merged', 'rows_after' => (int)$rowsAfter];
            echo " - merged, rows now: $rowsAfter\n";
        } catch (Exception $e) {
            $db->rollBack();
            $report[$table] = ['status' => 'error', 'message' => $e->getMessage()];
            echo " - ERROR merging table $table: " . $e->getMessage() . "\n";
        }
    }

    $db->exec("PRAGMA foreign_keys = ON");
    $res = $db->query("PRAGMA integrity_check")->fetchAll(PDO::FETCH_COLUMN);
    echo "PRAGMA integrity_check result:\n";
    foreach ($res as $r) echo " - $r\n";

    // save report
    $reportFile = dirname($dst) . DIRECTORY_SEPARATOR . 'merge_report_' . date('Ymd_His') . '.json';
    file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
    echo "Report saved to: $reportFile\n";

    // Detach src
    $db->exec("DETACH DATABASE src");

    echo "Done.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(6);
}
