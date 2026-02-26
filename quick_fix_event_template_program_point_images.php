<?php
// quick_fix_event_template_program_point_images.php

use Illuminate\Database\Capsule\Manager as DB;

require __DIR__.'/vendor/autoload.php';

// Konfiguracja bazy (jeśli nie używasz frameworka, podmień na PDO lub SQLite3)
$db = new DB();
$db->addConnection([
    'driver' => 'sqlite',
    'database' => __DIR__.'/database/database.sqlite',
]);
$db->setAsGlobal();
$db->bootEloquent();

$id = 231; // ID rekordu do poprawy
$featured = 'program-points/01JYM238B39EQ1VEF4TD1FXF72.png';
$gallery = [
    'program-points/gallery/01JYM2N81461HVA40ARK2P0QKV.png',
    'program-points/gallery/01JYM2S6N5RDYZK0R8B0PTFE7K.png',
];

DB::table('event_template_program_points')
    ->where('id', $id)
    ->update([
        'featured_image' => $featured,
        'gallery_images' => json_encode($gallery),
    ]);

echo "Zaktualizowano rekord $id\n";
