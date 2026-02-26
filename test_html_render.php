<?php
// Test renderowania HTML w Laravel/Blade

$testHtml = '<p>Test <strong>pogrubienia</strong> i <em>kursywy</em>.</p>';

echo "Surowy HTML:\n";
echo $testHtml . "\n\n";

echo "Z htmlspecialchars (jak {{ }}):\n";
echo htmlspecialchars($testHtml) . "\n\n";

echo "Bez escape (jak {!! !!}):\n";
echo $testHtml . "\n\n";

echo "Z strip_tags (dozwolone tagi):\n";
echo strip_tags($testHtml, '<p><strong><em><b><i>') . "\n\n";

echo "Z strip_tags i htmlspecialchars:\n";
echo htmlspecialchars(strip_tags($testHtml, '<p><strong><em><b><i>')) . "\n\n";
