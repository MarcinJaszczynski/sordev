{{--
  Przewijanie do panelu edycji po wyborze zadania.
  Lista często wydłuża stronę (brak sztywnego max-height) — po kliknięciu
  trzeba wrócić do góry split-view / formularza.
--}}
<script>
    window.scrollTasksSplitEditorIntoView = window.scrollTasksSplitEditorIntoView || function () {
        const split = document.querySelector('.tasks-split-view');
        const detail = document.querySelector('#tasks-split-detail-panel, .tasks-split-view__detail');
        const editor = document.querySelector('.task-split-detail__editor');
        const target = detail || split;

        if (editor) {
            editor.scrollTop = 0;
        }

        if (! target) {
            return;
        }

        const scrollElementToTarget = (scroller) => {
            if (! scroller) {
                return;
            }

            const tRect = target.getBoundingClientRect();
            const sRect = scroller === window || scroller === document.documentElement || scroller === document.body
                ? { top: 0 }
                : scroller.getBoundingClientRect();

            if (scroller === window) {
                window.scrollTo({
                    top: Math.max(0, window.scrollY + tRect.top - 8),
                    behavior: 'auto',
                });

                return;
            }

            if (scroller === document.documentElement || scroller === document.body) {
                const top = Math.max(0, (window.scrollY || document.documentElement.scrollTop || 0) + tRect.top - 8);
                document.documentElement.scrollTop = top;
                document.body.scrollTop = top;
                window.scrollTo({ top, behavior: 'auto' });

                return;
            }

            scroller.scrollTop += tRect.top - sRect.top - 8;
        };

        // Filament 3/4 — główny kontener treści
        document.querySelectorAll('.fi-main, .fi-main-ctn, .fi-body, .fi-page, [data-slot="content"]').forEach((el) => {
            if (el.scrollHeight > el.clientHeight + 1) {
                scrollElementToTarget(el);
            }
        });

        // Wszystkie scrollowalne przodkowie
        let node = target.parentElement;
        while (node && node !== document.documentElement) {
            const style = window.getComputedStyle(node);
            const overflowY = style.overflowY;
            const scrollable = (overflowY === 'auto' || overflowY === 'scroll' || overflowY === 'overlay')
                && node.scrollHeight > node.clientHeight + 1;

            if (scrollable) {
                scrollElementToTarget(node);
            }

            node = node.parentElement;
        }

        // Okno / dokument na końcu — gdy lista rozciągnęła stronę w dół
        scrollElementToTarget(window);
        target.scrollIntoView({ behavior: 'auto', block: 'start' });
    };
</script>
