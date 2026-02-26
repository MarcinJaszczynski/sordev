<?php
// import_sqlite_preserve_schema.php
// Bezpieczny import: kopiuj dane z pliku źródłowego do docelowego zachowując strukturę docelową.

// Allow overriding source and destination via CLI arguments
$src = $argv[1] ?? 'C:/Users/user/Documents/Herd/sor3events/database/database_26_08.sqlite';
$dst = $argv[2] ?? 'C:/Users/user/Documents/Herd/sor3events/database/database_serv.sqlite';

if (!file_exists($src)) {
    echo "Source DB not found: $src\n";
    exit(2);
}
if (!file_exists($dst)) {
    echo "Destination DB not found: $dst\n";
    exit(3);
}

$backup = $dst . '.bak.' . date('Ymd_His');
if (!@copy($dst, $backup)) {
    echo "Failed to create backup: $backup\n";
    exit(4);
}
echo "Backup created: $backup\n";

try {
    $db = new PDO('sqlite:' . $dst);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Attach source DB
    $db->exec("ATTACH DATABASE '$src' AS src");

    // Turn off foreign keys during import
    $db->exec("PRAGMA foreign_keys = OFF");

    // Get tables (excluding sqlite internal tables)
    $tablesSrc = $db->query("SELECT name FROM src.sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
    $tablesDst = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);

    echo "Tables in source: " . count($tablesSrc) . "\n";
    echo "Tables in destination: " . count($tablesDst) . "\n";

    $report = [];

    foreach ($tablesSrc as $table) {
        if (!in_array($table, $tablesDst)) {
            $report[$table] = ['status' => 'skipped_no_dest_table'];
            echo "Skipping table (not in dest): $table\n";
            continue;
        }

        // Get columns for both
        $colsSrcInfo = $db->query("PRAGMA src.table_info('$table')")->fetchAll(PDO::FETCH_ASSOC);
        $colsDstInfo = $db->query("PRAGMA table_info('$table')")->fetchAll(PDO::FETCH_ASSOC);

        $colsSrc = array_map(function($r){return $r['name'];}, $colsSrcInfo);
        $colsDst = array_map(function($r){return $r['name'];}, $colsDstInfo);

        $commonCols = array_values(array_intersect($colsDst, $colsSrc));
        if (empty($commonCols)) {
            $report[$table] = ['status' => 'no_common_columns'];
            echo "No common columns for table $table, skipping.\n";
            continue;
        }

        // Build column lists (quoted)
        $quotedCols = array_map(function($c){return '"'.str_replace('"','""',$c).'"';}, $commonCols);
        $colList = implode(', ', $quotedCols);
        $srcColList = implode(', ', $quotedCols); // same names, but referenced from src

        // Prepare and execute insert-or-replace to preserve IDs
        $sql = "INSERT OR REPLACE INTO \"$table\" ($colList) SELECT $srcColList FROM src.\"$table\";";

        $db->beginTransaction();
        $affected = 0;
        try {
            $db->exec($sql);
            $affected = $db->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
            $db->commit();
            $report[$table] = ['status' => 'ok', 'rows_after' => (int)$affected, 'cols' => $commonCols];
            echo "Imported table $table, rows now: $affected (columns: " . count($commonCols) . ")\n";
        } catch (Exception $e) {
            $db->rollBack();
            $report[$table] = ['status' => 'error', 'message' => $e->getMessage()];
            echo "Error importing table $table: " . $e->getMessage() . "\n";
        }
    }

    // Re-enable foreign keys
    $db->exec("PRAGMA foreign_keys = ON");

    // Run integrity check
    $res = $db->query("PRAGMA integrity_check")->fetchAll(PDO::FETCH_COLUMN);
    echo "PRAGMA integrity_check result:\n";
    foreach ($res as $r) echo " - $r\n";

    // Detach source
    $db->exec("DETACH DATABASE src");

    // Dump brief report as JSON to file
    $reportFile = dirname($dst) . DIRECTORY_SEPARATOR . 'import_report_' . date('Ymd_His') . '.json';
    file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
    echo "Report saved to: $reportFile\n";

    echo "Done.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(5);
}
