<?php
$dbPath = __DIR__ . '/../database/database.sqlite';
$db = new PDO('sqlite:' . $dbPath);

// Load program points linked to template 146
$sql = "SELECT etpp.*, piv.include_in_calculation as pivot_include_in_calculation, piv.id as pivot_id
FROM event_template_program_points etpp
JOIN event_template_event_template_program_point piv ON piv.event_template_program_point_id = etpp.id AND piv.event_template_id = 146
ORDER BY piv.day, piv.\"order\"";
$points = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Build associative array of point models
$pointsById = [];
foreach ($points as $p) {
    $pointsById[$p['id']] = $p;
}

// Load child relationships from event_template_program_point_parent
$childMap = [];
$sql = "SELECT parent_id, child_id FROM event_template_program_point_parent WHERE parent_id IN (SELECT event_template_program_point_id FROM event_template_event_template_program_point WHERE event_template_id = 146)";
$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $childMap[$r['parent_id']][] = $r['child_id'];
}

// Load child pivots (event_template_program_point_child_pivot)
$childPivots = [];
$sql = "SELECT * FROM event_template_program_point_child_pivot WHERE event_template_id = 146";
$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $childPivots[$r['program_point_child_id']] = $r;
}

// qty variants
$qtys = $db->query("SELECT id, qty, gratis, staff, driver FROM event_template_qties")->fetchAll(PDO::FETCH_ASSOC);
if (empty($qtys)) $qtys = [['qty'=>10,'gratis'=>1,'staff'=>0,'driver'=>0]];

foreach ($qtys as $qv) {
    $qty = $qv['qty'];
    $gratis = $qv['gratis'] ?? 0;
    echo "\n--- Variant qty={$qty} gratis={$gratis} ---\n";
    $plnPoints = [];
    foreach ($pointsById as $id => $p) {
        $pointIncluded = isset($p['pivot_include_in_calculation']) ? (bool)$p['pivot_include_in_calculation'] : true;
        if ($pointIncluded) {
            $plnPoints[] = "PARENT: {$p['name']} (id={$p['id']})";
        }
        // children
        $children = $childMap[$p['id']] ?? [];
        foreach ($children as $childId) {
            // load child data
            $child = $db->query("SELECT * FROM event_template_program_points WHERE id = " . intval($childId))->fetch(PDO::FETCH_ASSOC);
            $childPivot = $childPivots[$childId] ?? null;
            $childIncluded = $childPivot ? (bool)$childPivot['include_in_calculation'] : true;
            if ($childIncluded) {
                $plnPoints[] = "  CHILD: {$child['name']} (id={$child['id']})";
            }
        }
    }
    if (empty($plnPoints)) echo "(no points added)\n";
    else echo implode("\n", $plnPoints) . "\n";
}
