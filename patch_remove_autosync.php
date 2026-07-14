<?php
$content = file_get_contents('app/Livewire/EventHotelPlanEditor.php');

$content = preg_replace('/public bool \$samePlanAllNights = false;\n?/', '', $content);
$content = preg_replace('/\$this->samePlanAllNights = \$this->detectSamePlanAllNights\(\);\n?/', '', $content);
$content = preg_replace('/public function updatedSamePlanAllNights.*?}\n\n/s', '', $content);
$content = preg_replace('/public function syncActiveStayToAllNights.*?}\n\n/s', '', $content);
$content = preg_replace('/private function detectSamePlanAllNights.*?}\n\n/s', '', $content);

$content = preg_replace('/if \(\$this->samePlanAllNights && preg_match.*?}\n\n?/s', '', $content);

$content = str_replace('private function afterStayMutation(): void
    {
        $this->syncActiveStayToAllNights();
    }', 'private function afterStayMutation(): void
    {
        // No auto-sync
    }', $content);

$content = preg_replace('/if \(\$this->samePlanAllNights\) \{.*?unset\(\$stay\);\n\s+\}\n\n/s', '', $content);
$content = preg_replace('/\$this->samePlanAllNights = true;\n\s+/', '', $content);

file_put_contents('app/Livewire/EventHotelPlanEditor.php', $content);
echo "Removed samePlanAllNights logic.\n";
