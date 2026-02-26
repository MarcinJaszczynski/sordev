// Minimal drag & drop helper for Event Program points
document.addEventListener('DOMContentLoaded', function () {
    function getRelationReorderUrl() {
        // Filament relation manager reorder uses POST to current page + '/reorder'
        // We'll send to window.location.href + '/reorder' which works in Filament context for the relation
        return window.location.href.replace(/\/$/, '') + '/reorder';
    }

    function sendOrder(payload) {
        fetch(getRelationReorderUrl(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({ order: payload })
        }).then(r => {
            if (!r.ok) throw new Error('Reorder failed');
            return r.json();
        }).then(() => {
            window.dispatchEvent(new CustomEvent('eventProgramReordered'));
        }).catch(e => {
            console.error(e);
            window.dispatchEvent(new CustomEvent('eventProgramReorderFailed', { detail: { error: e.message } }));
        });
    }

    // Scope behavior to each DnD root
    document.querySelectorAll('.pp-dnd-root').forEach(function (root) {
        const list = root.querySelectorAll('.pp-dnd-list .pp-row');

        // Make Save button emit to Livewire
        const saveBtn = root.querySelector('.pp-dnd-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                const rows = Array.from(root.querySelectorAll('.pp-dnd-list .pp-row'));
                const payload = rows.map((row, index) => {
                    const id = row.getAttribute('data-pp-id');
                    const parent = row.getAttribute('data-parent-id') || null;
                    const day = row.getAttribute('data-day') || null;
                    const order = row.getAttribute('data-order') || index;
                    return { id: parseInt(id), parent_id: parent ? parseInt(parent) : null, day: day ? parseInt(day) : null, order: parseInt(order) };
                });

                if (window.livewire) {
                    window.livewire.emit('saveOrderFromDnDPayload', payload);
                } else {
                    // fallback: post to relation reorder
                    sendOrder(payload);
                }
            });
        }

        // Wire up move up/down buttons to call Livewire if available
        root.querySelectorAll('.pp-move-up').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                const row = e.target.closest('.pp-row');
                const id = row?.getAttribute('data-pp-id');
                if (window.livewire) window.livewire.emit('callMethod', 'moveUp', parseInt(id));
            });
        });

        root.querySelectorAll('.pp-move-down').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                const row = e.target.closest('.pp-row');
                const id = row?.getAttribute('data-pp-id');
                if (window.livewire) window.livewire.emit('callMethod', 'moveDown', parseInt(id));
            });
        });
    });
});
