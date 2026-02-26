<!-- Minimal DnD controls for Program Points relation manager -->
<div class="filament-program-dnd-controls my-4">
    <div class="flex items-center gap-2">
        <button type="button" class="filament-button-primary pp-dnd-save">Zapisz kolejność</button>
        <span class="text-sm text-gray-500">Po przeciągnięciu elementów kliknij "Zapisz kolejność" aby zapisać nowy porządek.</span>
    </div>
</div>

<!-- Load SortableJS from CDN (minimal, no build changes) and init behaviour -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const list = document.querySelector('.pp-dnd-list');
    if (!list) return;

    const sortable = new Sortable(list, {
        animation: 150,
        handle: '.drag-handle',
        onEnd: function (evt) {
            // update data-order attributes based on current DOM order
            document.querySelectorAll('.pp-dnd-list .pp-row').forEach(function (row, idx) {
                row.setAttribute('data-order', idx + 1);
            });
        }
    });

    document.querySelectorAll('.pp-dnd-save').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const rows = document.querySelectorAll('.pp-dnd-list .pp-row');
            const payload = [];
            rows.forEach(function (row) {
                const id = row.getAttribute('data-pp-id');
                const parent = row.getAttribute('data-parent-id') || null;
                const day = row.getAttribute('data-day') || null;
                const order = parseInt(row.getAttribute('data-order') || Array.prototype.indexOf.call(rows, row) + 1);
                payload.push({ id: parseInt(id), parent_id: parent ? parseInt(parent) : null, day: day ? parseInt(day) : null, order: order });
            });

            if (window.Livewire) {
                Livewire.emit('saveOrderFromDnDPayload', payload);
            } else {
                // fallback to fetch to /reorder if Livewire not present
                fetch(window.location.href.replace(/\/$/, '') + '/reorder', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({ order: payload })
                }).then(r => { if (!r.ok) throw new Error('Reorder failed'); return r.json(); })
                .then(() => window.dispatchEvent(new CustomEvent('eventProgramReordered')))
                .catch(e => { console.error(e); window.dispatchEvent(new CustomEvent('eventProgramReorderFailed', { detail: { error: e.message } })); });
            }
        });
    });
});
</script>
