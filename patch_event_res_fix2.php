<?php
$content = file_get_contents('app/Filament/Resources/EventResource.php');

$content = str_replace(
    'afterStateUpdated(function (callable $get, callable $set): void {',
    'afterStateUpdated(function (callable $get, callable $set, ?\App\Models\Event $record): void {',
    $content
);

$content = str_replace(
    '$templateId = (int) ($get(\'event_template_id\') ?? $livewire->getRecord()?->event_template_id ?? 0);',
    '$templateId = (int) ($get(\'event_template_id\') ?? $record?->event_template_id ?? 0);',
    $content
);

file_put_contents('app/Filament/Resources/EventResource.php', $content);
echo "Fixed closure parameters in EventResource.\n";
