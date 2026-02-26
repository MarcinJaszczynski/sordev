<?php
// quick_fix_gallery_images.php
// Skrypt naprawia nieprawidłowe wartości w polu gallery_images w tabeli event_template_program_points

$db = new SQLite3(__DIR__ . '/database/database.sqlite');

$result = $db->query('SELECT id, gallery_images FROM event_template_program_points');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $id = $row['id'];
    $gallery = $row['gallery_images'];
    if (!$gallery) continue;

    // Spróbuj zdekodować jako JSON
    $decoded = json_decode($gallery, true);
    if (is_array($decoded)) {
        // Jeśli już jest ok, pomiń
        continue;
    }

    // Spróbuj naprawić format ręcznie
    $fixed = $gallery;
    $fixed = str_replace(['\\', '"', ' ,', ', '], '', $fixed); // usuń ukośniki i spacje
    $fixed = preg_replace('/\s+/', '', $fixed); // usuń białe znaki
    $fixed = str_replace(['[', ']'], '', $fixed); // usuń nawiasy
    $arr = array_filter(explode(',', $fixed), function($v) { return trim($v) !== ''; });
    $arr = array_map(function($v) { return trim($v, '" '); }, $arr);
    // Filtruj tylko ścieżki do plików .png/.jpg/.jpeg/.webp/.gif
    $arr = array_filter($arr, function($v) {
        return preg_match('/\.(png|jpg|jpeg|webp|gif)$/i', $v);
    });
    $json = json_encode(array_values($arr), JSON_UNESCAPED_SLASHES);

    $db->exec("UPDATE event_template_program_points SET gallery_images = '" . SQLite3::escapeString($json) . "' WHERE id = $id");
    echo "Naprawiono rekord $id\n";
}

echo "Gotowe.\n";
