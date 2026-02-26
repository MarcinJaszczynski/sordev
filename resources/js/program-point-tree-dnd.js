import Sortable from 'sortablejs';

export function initProgramPointTreeDnD(ul, livewireComponent, parentId = null) {
    if (!ul) return;
    Sortable.create(ul, {
        group: 'program-points',
        animation: 150,
        handle: 'span',
        onEnd: function (evt) {
            const newParentId = ul.dataset.parentId ? parseInt(ul.dataset.parentId) : null;
            const orderedIds = Array.from(ul.children).map(li => li.dataset.id);
            livewireComponent.call('updateOrder', newParentId, orderedIds);
        },
    });
}
