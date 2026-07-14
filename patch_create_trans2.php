<?php
$content = file_get_contents('app/Filament/Forms/EventKeyInfoFields.php');

$content = preg_replace("/Forms\\\\Components\\\\TextInput::make\('program_km'\).*?event-price-table-refresh'\);\s+?\}\),/s", "", $content);
$content = preg_replace("/Forms\\\\Components\\\\TextInput::make\('transfer_km'\).*?event-price-table-refresh'\);\s+?\}\),/s", "", $content);

file_put_contents('app/Filament/Forms/EventKeyInfoFields.php', $content);
echo "Removed remaining fields from EventKeyInfoFields.\n";
