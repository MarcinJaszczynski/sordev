<?php
$files = [];
exec('grep -rl "RichEditor::make" app/Filament/', $files);

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    
    $content = file_get_contents($file);
    
    // Replace Filament's RichEditor with TiptapEditor
    $content = preg_replace('/\\\\?Filament\\\\Forms\\\\Components\\\\RichEditor::make/i', '\Awcodes\FilamentTiptapEditor\TiptapEditor::make', $content);
    $content = preg_replace('/Forms\\\\Components\\\\RichEditor::make/i', '\Awcodes\FilamentTiptapEditor\TiptapEditor::make', $content);
    
    // Some might have ->toolbarButtons(...) which aren't compatible with TiptapEditor, but let's remove it if it exists.
    // TiptapEditor uses ->profile('default') or just includes all tools.
    $content = preg_replace('/->toolbarButtons\([^)]+\)/', '', $content);
    
    file_put_contents($file, $content);
}

echo "Replaced all RichEditors.\n";
