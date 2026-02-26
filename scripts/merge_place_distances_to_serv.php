<?php
// Usage: php merge_place_distances_to_serv.php
// Copies missing place_distances from local database (database.sqlite) to server copy (database_serv.sqlite)
// Mapping of places is done by (1) exact lat/lon match (within small epsilon), (2) case-insensitive name match.

$srcPath = __DIR__ . '/../database/database.sqlite';
$dstPath = __DIR__ . '/../database/database_serv.sqlite';
$now = date('Ymd_His');

if (!file_exists($srcPath)) {
    echo "Source DB not found: {$srcPath}\n";
    exit(1);
}
if (!file_exists($dstPath)) {
    echo "Destination DB not found: {$dstPath}\n";
    exit(1);
}

// backup dst
$backup = $dstPath . '.bak.' . $now;
if (!copy($dstPath, $backup)) {
    echo "Failed to backup destination DB to {$backup}\n";
    exit(1);
}
echo "Backup created: {$backup}\n";

$src = new PDO('sqlite:' . $srcPath);
$dst = new PDO('sqlite:' . $dstPath);
$src->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dst->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// load places
$srcPlaces = [];
$stmt = $src->query("SELECT id, name, latitude, longitude FROM places");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $srcPlaces[$r['id']] = $r;
}
$dstPlaces = [];
$stmt = $dst->query("SELECT id, name, latitude, longitude FROM places");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dstPlaces[$r['id']] = $r;
}

$dstIndexByName = [];
foreach ($dstPlaces as $id => $p) {
    $nameKey = trim(strtolower($p['name'] ?? ''));
    if ($nameKey !== '') {
        if (!isset($dstIndexByName[$nameKey])) $dstIndexByName[$nameKey] = [];
        $dstIndexByName[$nameKey][] = $id;
    }
}

// helper to find dst id for src place
function findDstId($srcPlace, $dstPlaces, $dstIndexByName, $epsilon = 0.0001) {
    // try lat/lon exact-ish
    if ($srcPlace['latitude'] !== null && $srcPlace['longitude'] !== null && $srcPlace['latitude'] !== '' && $srcPlace['longitude'] !== '') {
        $lat1 = (float)$srcPlace['latitude'];
        $lon1 = (float)$srcPlace['longitude'];
        foreach ($dstPlaces as $did => $dp) {
            if ($dp['latitude'] === null || $dp['longitude'] === null || $dp['latitude']==='' || $dp['longitude']==='') continue;
            $lat2 = (float)$dp['latitude'];
            $lon2 = (float)$dp['longitude'];
            if (abs($lat1 - $lat2) <= $epsilon && abs($lon1 - $lon2) <= $epsilon) return $did;
        }
    }
    // try exact name case-insensitive
    $nameKey = trim(strtolower($srcPlace['name'] ?? ''));
    if ($nameKey !== '' && isset($dstIndexByName[$nameKey])) {
        // if multiple candidates return first
        return $dstIndexByName[$nameKey][0];
    }
    return null;
}

$mapping = []; // srcId => dstId or null
$unmapped = [];
foreach ($srcPlaces as $sid => $sp) {
    $mapped = findDstId($sp, $dstPlaces, $dstIndexByName);
    $mapping[$sid] = $mapped;
    if ($mapped === null) $unmapped[] = ['src_id' => $sid, 'name' => $sp['name'], 'lat' => $sp['latitude'], 'lon' => $sp['longitude']];
}

// report initial
$report = [
    'started_at' => date('c'),
    'src_path' => $srcPath,
    'dst_path' => $dstPath,
    'backup' => $backup,
    'places_src_count' => count($srcPlaces),
    'places_dst_count' => count($dstPlaces),
    'unmapped_places_count' => count($unmapped),
    'unmapped_sample' => array_slice($unmapped, 0, 50),
    'considered_pairs' => 0,
    'inserted' => 0,
    'skipped_existing' => 0,
    'skipped_unmapped' => 0,
];

// determine columns in dst place_distances table
$colsStmt = $dst->query("PRAGMA table_info('place_distances')");
$cols = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($cols, 'name');
$hasApiSource = in_array('api_source', $colNames);
$hasCreatedAt = in_array('created_at', $colNames);
$hasUpdatedAt = in_array('updated_at', $colNames);

// fetch source distances in a cursor fashion
$distStmt = $src->query("SELECT from_place_id, to_place_id, distance_km, api_source FROM place_distances WHERE distance_km IS NOT NULL AND distance_km > 0");
$dst->beginTransaction();
$nowTs = date('Y-m-d H:i:s');
while ($d = $distStmt->fetch(PDO::FETCH_ASSOC)) {
    $report['considered_pairs']++;
    $sFrom = (int)$d['from_place_id'];
    $sTo = (int)$d['to_place_id'];
    $dstFrom = $mapping[$sFrom] ?? null;
    $dstTo = $mapping[$sTo] ?? null;
    if ($dstFrom === null || $dstTo === null) {
        $report['skipped_unmapped']++;
        continue;
    }
    // check existence
    $check = $dst->prepare("SELECT COUNT(1) as c FROM place_distances WHERE from_place_id = :f AND to_place_id = :t");
    $check->execute([':f'=>$dstFrom,':t'=>$dstTo]);
    $c = (int)$check->fetchColumn();
    if ($c > 0) {
        $report['skipped_existing']++;
        continue;
    }
    // insert
    $fields = ['from_place_id','to_place_id','distance_km'];
    $placeholders = [':f',':t',':dist'];
    $params = [':f'=>$dstFrom,':t'=>$dstTo,':dist'=>$d['distance_km']];
    if ($hasApiSource) {
        $fields[] = 'api_source'; $placeholders[]=':api'; $params[':api']=$d['api_source'] ?? 'merged_from_local';
    }
    if ($hasCreatedAt) { $fields[]='created_at'; $placeholders[]=':ca'; $params[':ca']=$nowTs; }
    if ($hasUpdatedAt) { $fields[]='updated_at'; $placeholders[]=':ua'; $params[':ua']=$nowTs; }
    $sql = 'INSERT INTO place_distances (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
    $ins = $dst->prepare($sql);
    $ins->execute($params);
    $report['inserted']++;
}
$dst->commit();

$report['finished_at'] = date('c');
$reportPath = __DIR__ . "/merge_place_distances_report_{$now}.json";
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Done. Report: {$reportPath}\n";
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

exit(0);
