<?php
$content = file_get_contents('app/Filament/Resources/EventResource/Pages/CreateEvent.php');

// Remove from CreateEvent
$content = preg_replace("/Forms\\\\Components\\\\TextInput::make\('transfer_km'\).*?Forms\\\\Components\\\\TimePicker::make\('departure_time'\)/s", "Forms\\Components\\TimePicker::make('departure_time')", $content);

file_put_contents('app/Filament/Resources/EventResource/Pages/CreateEvent.php', $content);
echo "Fields removed from CreateEvent.\n";
