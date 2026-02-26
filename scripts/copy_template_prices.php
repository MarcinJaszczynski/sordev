<?php
$src = __DIR__ . '/../database/database_26_08.sqlite';
$dst = __DIR__ . '/../database/database.sqlite';
if (!file_exists($src) || !file_exists($dst)) {
    echo "Source or destination DB missing\n";
    exit(1);
}
$id = $argv[1] ?? null;
if (!$id) { echo "Usage: php copy_template_prices.php <event_template_id>\n"; exit(1); }
$ts = date('Ymd_His');
$bak = __DIR__ . "/../database/database.sqlite.bak.$ts";
copy($dst, $bak);
echo "Backup created: $bak\n";

$pdoSrc = new PDO('sqlite:' . $src);
$pdoDst = new PDO('sqlite:' . $dst);
$pdoSrc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdoDst->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function colsOf($pdo, $table) {
    $stmt = $pdo->query("PRAGMA table_info('$table')");
    $cols = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $cols[] = $r['name'];
    return $cols;
}

$table = 'event_template_price_per_person';
$srcCols = colsOf($pdoSrc, $table);
$dstCols = colsOf($pdoDst, $table);
$common = array_values(array_intersect($srcCols, $dstCols));
if (empty($common)) { echo "No common columns found for $table\n"; exit(1); }

// Exclude rowid alias if present
// Build insert statement
$colList = implode(', ', array_map(function($c){ return "`$c`"; }, $common));
$ph = implode(', ', array_fill(0, count($common), '?'));
$insertSql = "INSERT INTO $table ($colList) VALUES ($ph)";
$stmtIns = $pdoDst->prepare($insertSql);

// Fetch rows from src
$selCols = implode(', ', array_map(function($c){ return "`$c`"; }, $common));
$sel = $pdoSrc->prepare("SELECT $selCols FROM $table WHERE event_template_id = ?");
$sel->execute([$id]);
$rows = $sel->fetchAll(PDO::FETCH_NUM);
$total = count($rows);
if ($total === 0) { echo "No rows in source for event_template_id=$id\n"; exit(0); }

$inserted = 0;
$errors = 0;
$pdoDst->beginTransaction();
foreach ($rows as $r) {
    try {
        $stmtIns->execute($r);
        $inserted++;
    } catch (PDOException $e) {
        $errors++;
        // skip conflicts
        // echo "insert error: " . $e->getMessage() . "\n";
    }
}
$pdoDst->commit();

echo "Done: src_rows={$total}, inserted={$inserted}, errors={$errors}\n";
file_put_contents(__DIR__ . "/../database/copy_template_{$id}_report_$ts.json", json_encode(['src'=>$total,'inserted'=>$inserted,'errors'=>$errors], JSON_PRETTY_PRINT));

// Run quick check
$s = $pdoDst->prepare("SELECT COUNT(*) as cnt FROM $table WHERE event_template_id = ?");
$s->execute([$id]);
$cnt = $s->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
echo "Dst now has {$cnt} rows for event_template_id={$id}\n";

// integrity_check
echo "PRAGMA integrity_check: ";
echo $pdoDst->query('PRAGMA integrity_check')->fetchColumn() . "\n";

echo "Report saved.\n";
