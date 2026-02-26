<x-filament-panels::page>
    <div class="space-y-6">
        @foreach($this->getWidgets() as $widget)
            @livewire($widget, ['record' => $record])
        @endforeach
    </div>
</x-filament-panels::page>
