<?php
$src = __DIR__ . '/../database/database_26_08.sqlite';
$dst = __DIR__ . '/../database/database.sqlite';
foreach ([$src, $dst] as $p) { if (!file_exists($p)) { echo "DB missing: $p\n"; exit(1); }}
$pdoSrc = new PDO('sqlite:' . $src);
$pdoDst = new PDO('sqlite:' . $dst);
$pdoSrc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdoDst->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$id = $argv[1] ?? 282;
function q($pdo, $sql, $params = []) { $s = $pdo->prepare($sql); $s->execute($params); return $s; }

echo "Template id={$id}\n\n";
$tables = ['event_template_price_per_person', 'event_template_qties', 'event_template_price_per_person'];
// event_template_qties likely uses event_template_id? It might be event_template_id in pivot; we'll try both qties tables

// Check counts and sample rows
$checks = [
    ['table'=>'event_template_price_per_person','col'=>'event_template_id'],
    ['table'=>'event_template_qties','col'=>'event_template_id'],
    ['table'=>'event_template_qties','col'=>'id']
];

foreach ($checks as $c) {
    $table = $c['table'];
    $col = $c['col'];
    echo "Checking table $table (filter by $col)\n";
    try {
        $s1 = q($pdoSrc, "SELECT COUNT(*) as cnt FROM $table WHERE $col = ?", [$id]);
        $cnt1 = $s1->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
    } catch (Exception $e) { $cnt1 = "ERR: " . $e->getMessage(); }
    try {
        $s2 = q($pdoDst, "SELECT COUNT(*) as cnt FROM $table WHERE $col = ?", [$id]);
        $cnt2 = $s2->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
    } catch (Exception $e) { $cnt2 = "ERR: " . $e->getMessage(); }
    echo " - src: {$cnt1}  dst: {$cnt2}\n";
    if (is_numeric($cnt1) && $cnt1 > 0) {
        $r = q($pdoSrc, "SELECT * FROM $table WHERE $col = ? LIMIT 5", [$id])->fetchAll(PDO::FETCH_ASSOC);
        echo " - src samples:\n";
        foreach ($r as $row) {
            echo "   * ";
            $pairs = [];
            foreach ($row as $k=>$v) $pairs[] = "$k=" . (is_null($v)?'NULL':(strlen($v)>80?substr($v,0,80).'...':$v));
            echo implode(', ', $pairs) . "\n";
        }
    }
    if (is_numeric($cnt2) && $cnt2 > 0) {
        $r = q($pdoDst, "SELECT * FROM $table WHERE $col = ? LIMIT 5", [$id])->fetchAll(PDO::FETCH_ASSOC);
        echo " - dst samples:\n";
        foreach ($r as $row) {
            echo "   * ";
            $pairs = [];
            foreach ($row as $k=>$v) $pairs[] = "$k=" . (is_null($v)?'NULL':(strlen($v)>80?substr($v,0,80).'...':$v));
            echo implode(', ', $pairs) . "\n";
        }
    }
    echo "\n";
}

// Also list qties table full rows for template id via typical name event_template_qties might be event_template_qties with event_template_id column? Already attempted.

echo "Done.\n";
