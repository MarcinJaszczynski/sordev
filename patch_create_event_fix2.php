<?php
$content = file_get_contents('app/Filament/Resources/EventResource/Pages/CreateEvent.php');

$content = str_replace(
    'afterStateUpdated(function (callable $get, callable $set): void {',
    'afterStateUpdated(function (callable $get, callable $set, ?\App\Models\Event $record): void {',
    $content
);

file_put_contents('app/Filament/Resources/EventResource/Pages/CreateEvent.php', $content);
echo "Fixed closure parameters in CreateEvent.\n";
