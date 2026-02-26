<div
    x-data="{
        mode: '{{ $mode ?? 'multi' }}',
        selectId: '{{ $selectId }}',
        pageInputId: '{{ $pageInputId }}',
        selected: new Set(@json($selected ?? [])),
        getSelectEl() { return document.getElementById(this.selectId); },
        setNative() {
            const el = this.getSelectEl();
            if (!el) return;
            const values = Array.from(this.selected);
            el.value = this.mode === 'single' ? (values[0] ?? '') : JSON.stringify(values);
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        },
        sync() {
            // zawsze aktualizuj input ukryty
            this.setNative();
        },
        toggle(id) {
            if (this.mode === 'single') {
                this.selected = new Set([id]);
            } else {
                this.selected.has(id) ? this.selected.delete(id) : this.selected.add(id);
            }
            this.sync();
        },
        isSelected(id) { return this.selected.has(id); },
        goTo(page) {
            const el = document.getElementById(this.pageInputId);
            if (!el) return;
            el.value = page;
            el.dispatchEvent(new Event('input', { bubbles: true }));
        },
        init() {
            // spróbuj zsynchronizować po inicjalizacji i po krótkiej zwłoce (gdy TomSelect się zamontuje)
            this.sync();
            setTimeout(() => this.sync(), 0);
        }
    }"
    x-init="init()"
    class="space-y-3"
>
    <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px;">
        @foreach(($items ?? []) as $item)
            <div
                @click="toggle({{ $item['id'] }})"
                :class="isSelected({{ $item['id'] }}) ? 'ring-2 ring-offset-2 ring-primary-500' : 'ring-1 ring-gray-200'"
                class="cursor-pointer bg-white rounded-md overflow-hidden border border-gray-200 hover:shadow-sm transition relative"
                title="{{ $item['filename'] }}"
            >
                <img src="{{ $item['url'] }}" alt="{{ $item['filename'] }}" style="width:100%;height:100px;object-fit:cover;display:block;" />
                <div class="px-2 py-1 text-xs text-gray-700 truncate border-t border-gray-100 bg-gray-50">{{ $item['filename'] }}</div>
                <div x-show="isSelected({{ $item['id'] }})" class="absolute top-1 right-1 bg-primary-600 text-white text-[10px] px-1.5 py-0.5 rounded">Wybrane</div>
            </div>
        @endforeach
    </div>

    <div class="flex items-center justify-between text-sm text-gray-600">
        @php
            $total = $total ?? 0;
            $perPage = $perPage ?? 20;
            $page = max(1, (int) ($page ?? 1));
            $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
        @endphp
        <div>
            <button type="button" class="px-2 py-1 border rounded disabled:opacity-50" @click="goTo({{ max(1, $page - 1) }})" @disabled($page <= 1)>Poprzednia</button>
        </div>
        <div>Strona {{ $page }} z {{ $lastPage }} • {{ $total }} plików</div>
        <div>
            <button type="button" class="px-2 py-1 border rounded disabled:opacity-50" @click="goTo({{ min($lastPage, $page + 1) }})" @disabled($page >= $lastPage)>Następna</button>
        </div>
    </div>
</div>
