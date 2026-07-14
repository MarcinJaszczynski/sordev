<?php
$content = file_get_contents('app/Livewire/EventHotelPlanEditor.php');

$newMethod = <<<'PHP'
    public function initializeEmptyPlan(): void
    {
        $event = Event::findOrFail($this->eventId);
        app(EventHotelPlanService::class)->ensureStaysForEvent($event);
        $this->loadPlan();
        Notification::make()->title('Utworzono pusty plan hoteli')->success()->send();
    }

    public function restoreFromTemplate(): void
PHP;

$content = str_replace('public function restoreFromTemplate(): void', $newMethod, $content);
file_put_contents('app/Livewire/EventHotelPlanEditor.php', $content);
echo "Method added.\n";
