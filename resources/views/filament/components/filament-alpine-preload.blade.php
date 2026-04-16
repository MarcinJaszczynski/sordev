<script>
(() => {
    if (window.__filamentCorePreloaded) {
        return;
    }

    window.__filamentCorePreloaded = true;

    const preload = async () => {
        try {
            const [tableModule, selectModule] = await Promise.all([
                import(@js(\Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('table', 'filament/tables'))),
                import(@js(\Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('select', 'filament/forms'))),
            ]);

            const register = () => {
                if (! window.Alpine?.data) {
                    return false;
                }

                window.Alpine.data('table', tableModule?.default ?? tableModule);
                window.Alpine.data('selectFormComponent', selectModule?.default ?? selectModule);

                return true;
            };

            if (! register()) {
                document.addEventListener('alpine:init', register, { once: true });
            }
        } catch (error) {
            // Non-blocking preload; Filament x-load remains fallback.
            console.warn('Filament Alpine preload failed:', error);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', preload, { once: true });
    } else {
        preload();
    }
})();
</script>
