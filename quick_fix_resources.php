<?php

echo "🔧 Quick fix for all remaining Resource files\n";

$resourceFiles = [
    'CurrencyResource.php',
    'EventTemplateProgramPointResource.php', 
    'EventTemplateQtyResource.php',
    'EventTemplateResource.php',
    'KategoriaSzablonuResource.php',
    'PayerResource.php',
    'PaymentStatusResource.php',
    'PaymentTypeResource.php',
    'RoleResource.php',
    'TagResource.php',
    'TaskResource.php',
    'TodoStatusResource.php',
    'UserResource.php'
];

$fixedCount = 0;

foreach ($resourceFiles as $filename) {
    $filePath = __DIR__ . '/app/Filament/Resources/' . $filename;
    
    if (!file_exists($filePath)) {
        echo "❌ File not found: {$filename}\n";
        continue;
    }
    
    echo "📝 Processing: {$filename}\n";
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    // Replace ::class with ::route('path')
    $content = preg_replace(
        "/('index'\\s*=>\\s*Pages\\\\[A-Za-z]+)::class/",
        "$1::route('/')",
        $content
    );
    
    $content = preg_replace(
        "/('edit'\\s*=>\\s*Pages\\\\[A-Za-z]+)::class/",
        "$1::route('/{record}/edit')",
        $content
    );
    
    $content = preg_replace(
        "/('create'\\s*=>\\s*Pages\\\\[A-Za-z]+)::class/",
        "$1::route('/create')",
        $content
    );
    
    $content = preg_replace(
        "/('view'\\s*=>\\s*Pages\\\\[A-Za-z]+)::class/",
        "$1::route('/{record}')",
        $content
    );
    
    $content = preg_replace(
        "/('kanban'\\s*=>\\s*Pages\\\\[A-Za-z]+)::class/",
        "$1::route('/kanban')",
        $content
    );
    
    $content = preg_replace(
        "/('program'\\s*=>\\s*Pages\\\\[A-Za-z]+)::class/",
        "$1::route('/program')",
        $content
    );
    
    if ($content !== $originalContent) {
        if (file_put_contents($filePath, $content)) {
            echo "   ✅ Updated successfully\n";
            $fixedCount++;
        } else {
            echo "   ❌ Failed to write file\n";
        }
    } else {
        echo "   ℹ️ No changes needed\n";
    }
}

echo "🏁 Quick fix complete! Fixed: {$fixedCount} files\n";
