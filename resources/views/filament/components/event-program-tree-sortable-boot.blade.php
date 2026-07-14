@vite('resources/js/event-program-tree-sortable.js')

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rootId = @json($rootId ?? 'event-program-days-container');
            let teardown = null;

            const boot = () => {
                const root = document.getElementById(rootId);
                const livewireRoot = root?.closest('[wire\\:id]');
                const componentId = livewireRoot?.getAttribute('wire:id');

                if (!root || !componentId || typeof window.initEventProgramDayTreeSortable !== 'function') {
                    return;
                }

                if (typeof teardown === 'function') {
                    teardown();
                }

                teardown = window.initEventProgramDayTreeSortable(root, window.Livewire.find(componentId));
            };

            boot();

            if (typeof Livewire !== 'undefined') {
                Livewire.hook('morph.updated', () => setTimeout(boot, 80));
            }

            document.addEventListener('livewire:navigated', () => setTimeout(boot, 100));
        });
    </script>
@endpush
