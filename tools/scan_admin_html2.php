<?php
$h = file_get_contents(__DIR__ . '/admin_blog_posts.html');
$lines = explode("\n", $h);
$patterns = ['fi-actions','fi-page-actions','header-actions','CreateAction','Create','\/create','/admin/blog-posts/create','fi-list-actions','data-action="create"','filament-tables-actions'];
foreach ($patterns as $p) {
    foreach ($lines as $i => $line) {
        if (stripos($line, $p) !== false) {
            echo "--- Pattern: $p (line " . ($i+1) . ") ---\n";
            $start = max(0, $i-3);
            $end = min(count($lines)-1, $i+3);
            for ($j=$start;$j<=$end;$j++) {
                $num = str_pad($j+1,5,' ',STR_PAD_LEFT);
                echo "$num: " . $lines[$j] . "\n";
            }
            echo "\n";
        }
    }
}
