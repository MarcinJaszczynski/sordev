<x-filament-panels::page>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold">Program: {{ $record->name }}</h2>
        <a href="{{ \App\Filament\Resources\EventTemplateResource::getUrl('edit', ['record' => $record->id]) }}"
            class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-primary-50 hover:text-primary-700 hover:border-primary-300 transition-colors duration-200">
            <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
            Wróć do edycji szablonu
        </a>
    </div>
    {{-- Widget Kanban pojawi się automatycznie jako header/footer widget Filament --}}

    <div>
        <livewire:event-program-tree :event-template="$record" />
    </div>

</x-filament-panels::page>