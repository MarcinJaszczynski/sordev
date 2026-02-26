<?php
// scripts/check_transport_data.php
// Usage: php check_transport_data.php <event_template_id>
$id = $argv[1] ?? null;
if (!$id) { echo "Usage: php check_transport_data.php <event_template_id>\n"; exit(1); }
$dbFile = __DIR__ . '/../database/database.sqlite';
if (!file_exists($dbFile)) { echo "DB not found: $dbFile\n"; exit(2); }
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get starting place availabilities
$stmt = $pdo->prepare('SELECT * FROM event_template_starting_place_availability WHERE event_template_id = ?');
$stmt->execute([(int)$id]);
$avail = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper to find place by name containing
function findPlaceByName($pdo, $name) {
    $s = $pdo->prepare('SELECT id, name FROM places WHERE name LIKE ? LIMIT 10');
    $s->execute(["%$name%"]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

// Print availability
echo "Event template: $id\n";
echo "Starting place availabilities:\n";
if (empty($avail)) { echo "  (none)\n"; }
foreach ($avail as $a) {
    echo "  start_place_id={$a['start_place_id']} end_place_id={$a['end_place_id']} available={$a['available']} note={$a['note']}\n";
}

// Search for Kalisz and Poznań places
$kalisz = findPlaceByName($pdo, 'Kalisz');
$poznan = findPlaceByName($pdo, 'Pozna');

echo "\nPlaces matching 'Kalisz':\n";
foreach ($kalisz as $p) echo "  {$p['id']} - {$p['name']}\n";

echo "\nPlaces matching 'Pozna':\n";
foreach ($poznan as $p) echo "  {$p['id']} - {$p['name']}\n";

// For combinations, check place_distances
$placeIds = array_unique(array_merge(array_column($kalisz,'id'), array_column($poznan,'id')));
if (empty($placeIds)) { echo "\nNo Kalisz/Poznan places found.\n"; exit(0); }

echo "\nChecking place_distances for Kalisz<->Poznan combos:\n";
foreach ($kalisz as $k) {
    foreach ($poznan as $z) {
        $s = $pdo->prepare('SELECT * FROM place_distances WHERE place_from_id = ? AND place_to_id = ?');
        $s->execute([$k['id'],$z['id']]);
        $r = $s->fetchAll(PDO::FETCH_ASSOC);
        if (empty($r)) {
            echo '  No distance row for ' . $k['id'] . ' (' . $k['name'] . ') -> ' . $z['id'] . ' (' . $z['name'] . ')\n';
        } else {
            foreach ($r as $row) {
                $d = $row['distance_km'] ?? '(null)';
                $note = $row['note'] ?? '';
                $price = $row['price'] ?? '';
                echo '  Row id=' . $row['id'] . ' distance_km=' . $d . ' price=' . $price . ' note=' . $note . "\n";
            }
        }
        // reverse
        $s->execute([$z['id'],$k['id']]);
        $r2 = $s->fetchAll(PDO::FETCH_ASSOC);
        if (empty($r2)) {
            echo '  No distance row for ' . $z['id'] . ' (' . $z['name'] . ') -> ' . $k['id'] . ' (' . $k['name'] . ')\n';
        } else {
            foreach ($r2 as $row) {
                $d = $row['distance_km'] ?? '(null)';
                $price = $row['price'] ?? '';
                $note = $row['note'] ?? '';
                echo '  Row id=' . $row['id'] . ' distance_km=' . $d . ' price=' . $price . ' note=' . $note . "\n";
            }
        }
    }
}

// Also check for any place_distances with null/zero distance in whole table
$s = $pdo->query("SELECT COUNT(*) as cnt FROM place_distances WHERE distance_km IS NULL OR distance_km = 0");
$c = $s->fetch(PDO::FETCH_ASSOC);
echo "\nplace_distances with NULL or 0 distance: {$c['cnt']}\n";

echo "\nDone.\n";
