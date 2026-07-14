<?php
$files = [];
exec('grep -rn ")," app/Filament/ | cut -d: -f1 | sort | uniq', $files);

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    // Find "->columnSpanFull()\n    )," and similar patterns and fix them
    $content = preg_replace("/->columnSpanFull\(\)\s+\),/m", "->columnSpanFull(),", $content);
    $content = preg_replace("/->columnSpanFull\(\)\s+\)/m", "->columnSpanFull()", $content);
    $content = preg_replace("/->label\('Treść'\)\s+\),/m", "->label('Treść'),", $content);
    file_put_contents($file, $content);
}
echo "Syntax fixes applied.\n";
