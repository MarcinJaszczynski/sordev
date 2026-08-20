{{--
  Po Livewire morph Filament Action modal potrafi zostawić x-cloak albo
  zgubić widoczność wrappera mimo Alpine isOpen — formularz wtedy nie wiąże
  stanu (np. komentarz). Heal tylko gdy Alpine.isOpen === true (nie po klasie CSS).
--}}
<script>
    document.addEventListener('livewire:init', () => {
        const healActionModals = () => {
            document.querySelectorAll('.fi-modal[data-fi-modal-id$="-action"]').forEach((root) => {
                const id = root.getAttribute('data-fi-modal-id') || '';
                if (id.includes('table') || id.includes('bulk') || id.includes('infolist') || id.includes('form-component')) {
                    return;
                }

                const wrap = root.children[0];
                if (! wrap || ! window.Alpine) {
                    return;
                }

                let data = null;
                try {
                    data = Alpine.$data(root);
                } catch (e) {
                    return;
                }

                if (! data?.isOpen) {
                    if (wrap.style.display === 'block') {
                        wrap.style.removeProperty('display');
                    }

                    return;
                }

                if (wrap.hasAttribute('x-cloak')) {
                    wrap.removeAttribute('x-cloak');
                }

                root.querySelectorAll('[x-data]').forEach((el) => {
                    if (! el._x_dataStack) {
                        Alpine.initTree(el);
                    }
                });

                if (getComputedStyle(wrap).display === 'none') {
                    wrap.style.display = 'block';
                }
            });
        };

        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => {
                setTimeout(healActionModals, 50);
                setTimeout(healActionModals, 200);
            });
        });
    });
</script>
