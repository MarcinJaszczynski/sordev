<?php
$content = file_get_contents('app/Filament/Forms/EventKeyInfoFields.php');

$content = preg_replace("/Forms\\\\Components\\\\Select::make\('start_place_id'\).*?event-price-table-refresh'\);\s+?\}\),/s", "", $content);

file_put_contents('app/Filament/Forms/EventKeyInfoFields.php', $content);
echo "Removed from EventKeyInfoFields.\n";
