<?php
$content = file_get_contents('app/Filament/Resources/EventResource.php');

$content = str_replace(
    'visible(fn (callable $get) => empty($get(\'event_template_id\')))',
    'visible(fn (callable $get, ?\App\Models\Event $record) => empty($get(\'event_template_id\')) && !($record?->event_template_id))',
    $content
);

$content = str_replace(
    '$templateId = (int) ($get(\'event_template_id\') ?? 0);',
    '$templateId = (int) ($get(\'event_template_id\') ?? $livewire->getRecord()?->event_template_id ?? 0);',
    $content
);

file_put_contents('app/Filament/Resources/EventResource.php', $content);
echo "Fixed visibility and template ID retrieval.\n";
