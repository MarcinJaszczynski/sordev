<?php
$h = file_get_contents(__DIR__ . '/admin_blog_posts.html');
$pat = '/(fi-page-header|fi-page-actions|fi-actions|fi-toolbar|fi-cta|CreateAction|Create)/i';
if (preg_match_all($pat, $h, $m, PREG_OFFSET_CAPTURE)) {
    echo "Matches: " . count($m[0]) . "\n";
    foreach ($m[0] as $match) {
        $pos = $match[1];
        $start = max(0, $pos-120);
        $snippet = substr($h, $start, 240);
        echo "--- snippet ---\n" . $snippet . "\n\n";
    }
} else {
    echo "No matches\n";
}
