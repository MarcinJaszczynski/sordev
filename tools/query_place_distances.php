<?php
require __DIR__ . '/../vendor/autoload.php';
// Determine sqlite path: prefer env DB_DATABASE, fallback to database/database.sqlite
$envContent = file_exists(__DIR__ . '/../.env') ? file_get_contents(__DIR__ . '/../.env') : '';
$dbMatch = null;
if (preg_match('/^DB_DATABASE=(.+)$/m', $envContent, $m)) {
    $dbMatch = trim($m[1], " \"'");
}
$db = $dbMatch ?: __DIR__ . '/../database/database.sqlite';
// Setup Eloquent capsule
use Illuminate\Database\Capsule\Manager as Capsule;
$capsule = new Capsule();
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => $db,
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$pdo = $capsule->getConnection()->getPdo();
$stmt = $pdo->query("SELECT pd.id, pd.from_place_id, pd.to_place_id, pd.distance_km, pd.api_source, p1.name as from_name, p2.name as to_name FROM place_distances pd LEFT JOIN places p1 ON p1.id = pd.from_place_id LEFT JOIN places p2 ON p2.id = pd.to_place_id ORDER BY p1.name, p2.name LIMIT 200");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
