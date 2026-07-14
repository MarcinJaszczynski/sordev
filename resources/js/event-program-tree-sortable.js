import Sortable from 'sortablejs';

function destroyInstances(instances) {
    instances.forEach((instance) => {
        try {
            instance.destroy();
        } catch (e) {
            // ignore
        }
    });
}

export function initEventProgramDayTreeSortable(root, livewire) {
    if (!root || typeof Sortable === 'undefined' || !livewire) {
        return () => {};
    }

    const daySortables = [];
    const childSortables = [];

    root.querySelectorAll('.program-day-list').forEach((listEl) => {
        daySortables.push(
            new Sortable(listEl, {
                group: 'event-program-day-blocks',
                animation: 160,
                handle: '.drag-handle:not(.child-drag)',
                draggable: '.program-point-item',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dataIdAttr: 'data-id',
                onEnd() {
                    const dayId = parseInt(listEl.dataset.dayId || '0', 10);
                    const orderedParentIds = Array.from(
                        listEl.querySelectorAll(':scope > .program-point-item'),
                    )
                        .map((el) => el.dataset.id)
                        .filter(Boolean);

                    livewire.reorderDayBlocks(dayId, orderedParentIds);
                },
            }),
        );
    });

    root.querySelectorAll('.program-set-children-list').forEach((listEl) => {
        childSortables.push(
            new Sortable(listEl, {
                group: 'event-program-set-children',
                animation: 150,
                handle: '.child-drag',
                draggable: '.program-set-child-item',
                ghostClass: 'sortable-ghost',
                onEnd() {
                    const parentId = parseInt(listEl.dataset.parentId || '0', 10);
                    const orderedChildIds = Array.from(
                        listEl.querySelectorAll(':scope > .program-set-child-item'),
                    )
                        .map((el) => el.dataset.id)
                        .filter(Boolean);

                    livewire.reorderSetChildren(parentId, orderedChildIds);
                },
            }),
        );
    });

    return () => destroyInstances([...daySortables, ...childSortables]);
}

if (typeof window !== 'undefined') {
    window.initEventProgramDayTreeSortable = initEventProgramDayTreeSortable;
}
