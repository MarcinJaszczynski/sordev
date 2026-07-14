@vite('resources/js/filament-sortable.js')

@once
    @push('scripts')
        <script>
            window.sorRunWhenSortableReady = window.sorRunWhenSortableReady || function (fn) {
                const tick = () => {
                    if (typeof window.Sortable !== 'undefined') {
                        fn();
                        return;
                    }
                    setTimeout(tick, 40);
                };
                tick();
            };
        </script>
    @endpush
@endonce
