<?php
// safe_merge_rows_v2.php
// Uses two separate PDO connections (src and dst) to copy missing rows from src -> dst
// Keeps destination schema, inserts only rows whose PK doesn't exist in dst (if single PK),
// otherwise does INSERT OR IGNORE for intersection columns.

if ($argc < 3) {
    echo "Usage: php safe_merge_rows_v2.php <src_db> <dst_db>\n";
    exit(2);
}
$srcFile = $argv[1];
$dstFile = $argv[2];
if (!file_exists($srcFile)) { echo "Source not found: $srcFile\n"; exit(3); }
if (!file_exists($dstFile)) { echo "Destination not found: $dstFile\n"; exit(4); }

$backup = $dstFile . '.bak.' . date('Ymd_His');
if (!@copy($dstFile, $backup)) {
    echo "Failed to create backup: $backup\n";
    exit(5);
}
echo "Backup created: $backup\n";

$src = new PDO('sqlite:' . $srcFile);
$dst = new PDO('sqlite:' . $dstFile);
$src->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dst->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tablesSrc = $src->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
$tablesDst = $dst->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);

$report = [];

foreach ($tablesSrc as $table) {
    echo "Processing table: $table\n";
    if (!in_array($table, $tablesDst)) {
        echo " - table not present in dst, skipping (we keep dst schema unchanged)\n";
        $report[$table] = ['status' => 'no_table_in_dst'];
        continue;
    }

    // Get columns
    $colsSrcInfo = $src->query("PRAGMA table_info('$table')")->fetchAll(PDO::FETCH_ASSOC);
    $colsDstInfo = $dst->query("PRAGMA table_info('$table')")->fetchAll(PDO::FETCH_ASSOC);
    $colsSrc = array_map(fn($r) => $r['name'], $colsSrcInfo);
    $colsDst = array_map(fn($r) => $r['name'], $colsDstInfo);

    $commonCols = array_values(array_intersect($colsDst, $colsSrc));
    if (empty($commonCols)) { echo " - no common columns, skipping\n"; $report[$table]=['status'=>'no_common_cols']; continue; }

    // Determine PK
    $pkCols = array_values(array_filter($colsDstInfo, fn($c) => (int)$c['pk'] > 0));
    $pkNames = array_map(fn($c) => $c['name'], $pkCols);

    $selectCols = implode(', ', array_map(fn($c) => '"'.$c.'"', $commonCols));
    $placeholders = implode(', ', array_map(fn($c) => ':' . $c, $commonCols));
    $insertCols = implode(', ', array_map(fn($c) => '"'.$c.'"', $commonCols));

    $srcStmt = $src->prepare("SELECT $selectCols FROM \"$table\"");
    $srcStmt->execute();

    $insertSql = "INSERT INTO \"$table\" ($insertCols) VALUES ($placeholders)";
    $insertStmt = $dst->prepare($insertSql);

    $countInserted = 0;
    $countTotal = 0;
    $dst->beginTransaction();
    while ($row = $srcStmt->fetch(PDO::FETCH_ASSOC)) {
        $countTotal++;
        // check existence
        $exists = false;
        if (count($pkNames) == 1) {
            $pk = $pkNames[0];
            if (!isset($row[$pk])) {
                // no pk value in source row, fallback to attempt insert-or-ignore
                $checkSql = null;
            } else {
                $check = $dst->prepare("SELECT 1 FROM \"$table\" WHERE \"$pk\" = :v LIMIT 1");
                $check->execute([':v' => $row[$pk]]);
                if ($check->fetchColumn()) { $exists = true; }
            }
        }

        if ($exists) continue;

        // bind parameters for insert (use only commonCols)
        foreach ($commonCols as $c) {
            $insertStmt->bindValue(':' . $c, array_key_exists($c, $row) ? $row[$c] : null);
        }
        try {
            $insertStmt->execute();
            $countInserted++;
        } catch (Exception $e) {
            // if insert fails due to missing columns or types, log and continue
            echo "  - insert error for table $table: " . $e->getMessage() . "\n";
            // try next row
        }
    }
    $dst->commit();
    $report[$table] = ['status' => 'done', 'rows_in_src' => $countTotal, 'inserted' => $countInserted];
    echo " - done: rows in src=$countTotal, inserted=$countInserted\n";
}

// integrity check
$res = $dst->query('PRAGMA integrity_check')->fetchAll(PDO::FETCH_COLUMN);
echo "PRAGMA integrity_check result:\n"; foreach ($res as $r) echo " - $r\n";

$reportFile = dirname($dstFile) . DIRECTORY_SEPARATOR . 'safe_merge_report_' . date('Ymd_His') . '.json';
file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
echo "Report saved to: $reportFile\n";

echo "Done.\n";
