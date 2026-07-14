<?php
$files = [];
exec('grep -rl "Awcodes\\\\FilamentTiptapEditor" app/Filament/ app/Providers/', $files);

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $content = str_replace('Awcodes\\FilamentTiptapEditor', 'FilamentTiptapEditor', $content);
    file_put_contents($file, $content);
}
echo "Namespace fixed.\n";
