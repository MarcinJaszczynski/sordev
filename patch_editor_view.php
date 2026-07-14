<?php
$content = file_get_contents('resources/views/livewire/event-hotel-plan-editor.blade.php');

$newHtml = <<<'HTML'
    @if (empty($stays))
        <x-filament::section>
            <p class="text-sm text-gray-600 mb-4">Plan hoteli jest obecnie pusty. Jeśli impreza trwa dłużej niż jeden dzień, możesz wygenerować puste noce, lub przywrócić plan z szablonu.</p>
            <div class="flex flex-wrap gap-2">
                @if (($event->duration_days ?? 1) > 1)
                    <x-filament::button wire:click="initializeEmptyPlan">Rozpocznij planowanie ({{ max(1, $event->duration_days - 1) }} nocy)</x-filament::button>
                @endif
                <x-filament::button wire:click="restoreFromTemplate" color="gray">Utwórz plan z szablonu</x-filament::button>
            </div>
        </x-filament::section>
    @else
HTML;

$content = preg_replace('/@if \(empty\(\$stays\)\).*?@else/s', $newHtml, $content);
file_put_contents('resources/views/livewire/event-hotel-plan-editor.blade.php', $content);
echo "Replaced in view.\n";
