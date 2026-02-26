<?php
// Simple SQLite merge tool: copy rows from source DB into target DB
// Usage: php merge_sqlite.php /absolute/path/to/source.sqlite /absolute/path/to/target.sqlite

if ($argc < 3) {
    echo "Usage: php merge_sqlite.php /path/source.sqlite /path/target.sqlite\n";
    exit(1);
}
$sourcePath = $argv[1];
$targetPath = $argv[2];

if (!file_exists($sourcePath)) {
    echo "Source file not found: $sourcePath\n";
    exit(2);
}
if (!file_exists($targetPath)) {
    echo "Target file not found: $targetPath\n";
    exit(3);
}

echo "Source: $sourcePath\n";
echo "Target: $targetPath\n";

try {
    $src = new PDO('sqlite:' . $sourcePath);
    $tgt = new PDO('sqlite:' . $targetPath);
    $src->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $tgt->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo "DB open error: " . $e->getMessage() . "\n";
    exit(4);
}

function listTables(PDO $db) {
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getColumns(PDO $db, $table) {
    $stmt = $db->query("PRAGMA table_info(" . str_replace('"', '""', $table) . ");");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return array_map(fn($c) => $c['name'], $cols);
}

$sourceTables = listTables($src);
$targetTables = listTables($tgt);

echo "Source tables: " . implode(', ', $sourceTables) . "\n";
echo "Target tables: " . implode(', ', $targetTables) . "\n";

$common = array_intersect($sourceTables, $targetTables);
if (empty($common)) {
    echo "No common tables to merge.\n";
    exit(0);
}

echo "Common tables to consider: " . implode(', ', $common) . "\n";

$summary = [];

foreach ($common as $table) {
    echo "\nProcessing table: $table\n";
    $srcCols = getColumns($src, $table);
    $tgtCols = getColumns($tgt, $table);
    $commonCols = array_values(array_intersect($srcCols, $tgtCols));
    if (empty($commonCols)) {
        echo "  No matching columns, skipping.\n";
        $summary[$table] = ['skipped' => true, 'reason' => 'no common columns'];
        continue;
    }
    echo "  Common columns: " . implode(', ', $commonCols) . "\n";

    // Build select and insert
    $colList = implode(', ', array_map(function($c){ return '"'.$c.'"'; }, $commonCols));
    $placeholders = implode(', ', array_map(function($c){ return ':' . $c; }, $commonCols));

    // Fetch rows from source
    $countStmt = $src->query("SELECT COUNT(1) as cnt FROM \"$table\"");
    $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "  Source rows: $total\n";
    if ($total === 0) {
        $summary[$table] = ['inserted' => 0, 'skipped' => false];
        continue;
    }

    // We'll insert using INSERT OR IGNORE to avoid PK/unique conflicts.
    $insertSql = "INSERT OR IGNORE INTO \"$table\" ($colList) VALUES ($placeholders)";
    $insertStmt = $tgt->prepare($insertSql);

    // Stream rows in chunks
    $batch = 0;
    $inserted = 0;
    $chunk = 500;
    $offset = 0;
    while ($offset < $total) {
        $q = $src->prepare("SELECT $colList FROM \"$table\" LIMIT :lim OFFSET :off");
        $q->bindValue(':lim', $chunk, PDO::PARAM_INT);
        $q->bindValue(':off', $offset, PDO::PARAM_INT);
        $q->execute();
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) break;

        $tgt->beginTransaction();
        foreach ($rows as $r) {
            // bind values by column name
            foreach ($commonCols as $c) {
                $insertStmt->bindValue(':' . $c, isset($r[$c]) ? $r[$c] : null);
            }
            try {
                $ok = $insertStmt->execute();
                if ($ok) $inserted += 1;
            } catch (Exception $e) {
                // log and continue
                echo "    insert error: " . $e->getMessage() . "\n";
            }
        }
        $tgt->commit();
        $offset += count($rows);
        $batch++;
        echo "    Batch $batch: processed " . min($offset, $total) . "/$total rows, inserted so far: $inserted\r";
    }
    echo "\n  Done table $table: inserted approx $inserted rows.\n";
    $summary[$table] = ['inserted' => $inserted, 'skipped' => false];
}

echo "\nSummary:\n";
foreach ($summary as $table => $info) {
    echo " - $table: ";
    if ($info['skipped']) echo "skipped ({$info['reason']})\n"; else echo "inserted={$info['inserted']}\n";
}

echo "\nMerge complete.\n";
