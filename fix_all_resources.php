<?php

echo "🔧 Fixing all Resource getPages() methods\n";
echo "=" . str_repeat("=", 50) . "\n";

$resourceDir = __DIR__ . '/app/Filament/Resources';
$resourceFiles = glob($resourceDir . '/*Resource.php');

$fixedCount = 0;
$totalCount = 0;

foreach ($resourceFiles as $file) {
    $filename = basename($file);
    $totalCount++;
    
    echo "\n📝 Processing: {$filename}\n";
    
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Pattern to match getPages() method with ::class format
    $pattern = '/(\s+public static function getPages\(\): array\s*\{\s*return\s*\[\s*)(.*?)(\s*\];\s*\})/ms';
    
    if (preg_match($pattern, $content, $matches)) {
        $beforePages = $matches[1];
        $pagesContent = $matches[2];
        $afterPages = $matches[3];
        
        echo "   ✅ Found getPages() method\n";
        
        // Replace ::class with ::route('appropriate_path')
        $updatedPagesContent = preg_replace_callback(
            '/[\'"]([^\'"]++)[\'"] => ([A-Za-z\\\\]++Pages\\\\[A-Za-z]++)::class/m',
            function ($matches) {
                $key = $matches[1];
                $pageClass = $matches[2];
                
                // Determine the route based on the key and page class
                $route = match($key) {
                    'index' => '/',
                    'create' => '/create',
                    'edit' => '/{record}/edit',
                    'view' => '/{record}',
                    'kanban' => '/kanban',
                    'program' => '/program',
                    default => '/' . $key,
                };
                
                echo "      🔄 '{$key}' => {$pageClass}::class -> {$pageClass}::route('{$route}')\n";
                
                return "'{$key}' => {$pageClass}::route('{$route}')";
            },
            $pagesContent
        );
        
        if ($updatedPagesContent !== $pagesContent) {
            $newContent = $beforePages . $updatedPagesContent . $afterPages;
            $finalContent = str_replace($matches[0], $newContent, $content);
            
            if (file_put_contents($file, $finalContent)) {
                echo "   ✅ File updated successfully\n";
                $fixedCount++;
            } else {
                echo "   ❌ Failed to write file\n";
            }
        } else {
            echo "   ℹ️ No changes needed (already using ::route() format)\n";
        }
    } else {
        echo "   ⚠️ getPages() method not found or not in expected format\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🏁 Processing complete!\n";
echo "📊 Fixed: {$fixedCount}/{$totalCount} files\n";
