<x-filament-panels::page>
    @vite('resources/js/program-point-tree-dnd-entry.js')
    <div class="space-y-6">
        <div class="fi-section-content">
            <div class="fi-section-content-ctn bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 rounded-xl">
                <div class="p-6">
                    <livewire:event-template-program-point-tree-expandable />
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
