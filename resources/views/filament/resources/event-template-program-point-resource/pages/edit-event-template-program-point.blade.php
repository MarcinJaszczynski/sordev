<x-filament-panels::page>
    <div class="space-y-6">
        <!-- ...usunięto podgląd zdjęć... -->
        <!-- Główny formularz edycji -->
        <x-filament-panels::form wire:submit="save">
            {{ $this->form }}

            <x-filament-panels::form.actions 
                :actions="$this->getCachedFormActions()" 
                :full-width="$this->hasFullWidthFormActions()" 
            />
        </x-filament-panels::form>
        <!-- Sekcja podpunktów -->
        <div class="mt-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Zarządzanie podpunktami</h2>
            @livewire('program-point-children-editor', ['programPoint' => $record], key($record->id))
        </div>
    </div>
</x-filament-panels::page>
