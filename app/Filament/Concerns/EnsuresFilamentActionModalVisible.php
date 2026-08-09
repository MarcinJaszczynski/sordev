<?php

namespace App\Filament\Concerns;

/**
 * Livewire morph czasem zostawia x-cloak na wrapperze Filament Action modal
 * albo nie inicjuje zagnieżdżonego Alpine ($entangle w Textarea) — formularz
 * nie zapisuje stanu, walidacja pada, modal zostaje „otwarty”.
 *
 * Nie używamy Alpine.destroyTree() na root — psuje $wire.$entangle.
 */
trait EnsuresFilamentActionModalVisible
{
    protected function ensureMountedActionModalVisible(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $modalId = json_encode($this->getId().'-action', JSON_THROW_ON_ERROR);

        // setTimeout: efekt xjs odpala się zanim morph dokończy HTML modala.
        $this->js(<<<JS
            setTimeout(() => {
                const modalId = {$modalId};
                const root = document.querySelector('[data-fi-modal-id="' + modalId + '"]');
                if (! root) {
                    return;
                }

                root.querySelectorAll('[x-cloak]').forEach((el) => el.removeAttribute('x-cloak'));

                if (window.Alpine) {
                    root.querySelectorAll('[x-data]').forEach((el) => {
                        if (! el._x_dataStack) {
                            Alpine.initTree(el);
                        }
                    });
                }

                const data = window.Alpine ? Alpine.\$data(root) : null;
                if (data && typeof data.open === 'function') {
                    data.open();
                } else {
                    window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: modalId } }));
                }

                const wrap = root.children[0];
                if (wrap && data && data.isOpen && getComputedStyle(wrap).display === 'none') {
                    wrap.style.display = 'block';
                }
            }, 150);
        JS);
    }
}
