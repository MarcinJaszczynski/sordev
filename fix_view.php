<?php
$content = file_get_contents('resources/views/livewire/event-hotel-plan-editor.blade.php');

$content = preg_replace('/@if \(count\(\$stays\) > 1\).*?<label class="flex items-start.*?<\/label>\s*@endif/s', '', $content);
$content = preg_replace('/@if \(\$samePlanAllNights\).*?@endif/s', '', $content);

file_put_contents('resources/views/livewire/event-hotel-plan-editor.blade.php', $content);
