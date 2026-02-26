<?php
$dst = __DIR__ . '/../database/database.sqlite';
$src = __DIR__ . '/../database/database_26_08.sqlite';
$pdo = new PDO('sqlite:' . $dst);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdoSrc = new PDO('sqlite:' . $src);
$pdoSrc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$table = 'event_template_price_per_person';

echo "PRAGMA table_info($table)\n";
foreach ($pdo->query("PRAGMA table_info('$table')")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo " - {$r['cid']}: {$r['name']} ({$r['type']}) pk={$r['pk']}\n";
}

echo "\nPRAGMA index_list($table)\n";
$indexes = $pdo->query("PRAGMA index_list('$table')")->fetchAll(PDO::FETCH_ASSOC);
foreach ($indexes as $idx) {
    echo " - name={$idx['name']} unique={$idx['unique']} origin={$idx['origin']} partial={$idx['partial']}\n";
    $info = $pdo->query("PRAGMA index_info('{$idx['name']}')")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($info as $i) echo "   * seqno={$i['seqno']} cid={$i['cid']} name={$i['name']}\n";
}

// Grab a sample row from source and look for matching composite in dst
$samp = $pdoSrc->query("SELECT event_template_id, event_template_qty_id, currency_id, start_place_id FROM $table WHERE event_template_id = 282 LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
if (!$samp) { echo "No samples in src\n"; exit; }

foreach ($samp as $idx => $row) {
    echo "\nSample #".($idx+1).": ".json_encode($row)."\n";
    $q = $pdo->prepare("SELECT COUNT(*) as cnt, id FROM $table WHERE event_template_id = ? AND event_template_qty_id = ? AND currency_id = ? AND start_place_id = ? LIMIT 1");
    $q->execute([$row['event_template_id'],$row['event_template_qty_id'],$row['currency_id'],$row['start_place_id']]);
    $found = $q->fetch(PDO::FETCH_ASSOC);
    echo " - dst match count: " . ($found['cnt'] ?? 0) . " id=" . ($found['id'] ?? 'NULL') . "\n";
}

// Also list any rows in dst that have same composite but different event_template_id (just in case):
echo "\nSearching dst for any rows with same qty,currency,start_place (regardless of event_template_id) for first sample...\n";
$r = $samp[0];
$q = $pdo->prepare("SELECT COUNT(*) as cnt FROM $table WHERE event_template_qty_id = ? AND currency_id = ? AND start_place_id = ?");
$q->execute([$r['event_template_qty_id'],$r['currency_id'],$r['start_place_id']]);
echo " - dst rows with same qty/currency/start_place: " . $q->fetchColumn() . "\n";

echo "\nDone.\n";
