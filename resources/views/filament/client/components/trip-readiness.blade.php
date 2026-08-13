@php
    $items = $items ?? [];
@endphp

@if($items !== [])
    <section class="client-portal-section mb-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="client-portal-kicker">Gotowość</p>
            @if(! empty($contactUrl))
                <a href="{{ $contactUrl }}" class="text-xs font-semibold text-[#0663fc] hover:underline">Napisz do biura →</a>
            @endif
        </div>
        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($items as $item)
                <a href="{{ $item['url'] ?: '#' }}"
                   @class([
                       'rounded-xl border px-3 py-2.5 transition hover:border-blue-200 hover:bg-blue-50/40',
                       'border-emerald-200 bg-emerald-50/50' => ($item['status'] ?? '') === 'ok',
                       'border-amber-200 bg-amber-50/50' => ($item['status'] ?? '') === 'warn',
                       'border-slate-200 bg-slate-50/60' => ($item['status'] ?? '') === 'na',
                   ])>
                    <div class="text-xs font-semibold text-slate-800">{{ $item['label'] }}</div>
                    <div class="mt-0.5 text-[11px] text-slate-600">{{ $item['detail'] }}</div>
                </a>
            @endforeach
        </div>
    </section>
@endif
