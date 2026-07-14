<?php
$content = file_get_contents('app/Filament/Resources/EventResource.php');

$content = preg_replace("/Forms\\\\Components\\\\TextInput::make\('transfer_km'\).*?Forms\\\\Components\\\\Select::make\('markup_id'\)/s", "...EventTransportFields::manualTransportCostFields(),\n\n                Forms\\Components\\Select::make('markup_id')", $content);

file_put_contents('app/Filament/Resources/EventResource.php', $content);
echo "Fields removed from EventResource.\n";
