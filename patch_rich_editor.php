<?php
$files = [];
exec('grep -rn "RichEditor::make" app/Filament/ | cut -d: -f1 | sort | uniq', $files);

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    
    $content = file_get_contents($file);
    
    // We want to add ->toolbarButtons(['bold', 'italic', 'underline', 'strike', 'link', 'color', 'highlight', 'undo', 'redo', 'bulletList', 'orderedList', 'h2', 'h3'])
    // But some might already have toolbarButtons. Let's create a macro instead in a service provider.
}
