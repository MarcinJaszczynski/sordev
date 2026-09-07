@php
    $items = $items ?? [];
@endphp

@if($items !== [])
    <section class="portal-card">
        <div class="portal-card-title">
            <p>Gotowość</p>
            @if(! empty($contactUrl))
                <a href="{{ $contactUrl }}" class="portal-link text-xs">Napisz do biura →</a>
            @endif
        </div>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($items as $item)
                <a href="{{ $item['url'] ?: '#' }}"
                   @class([
                       'rounded-[8px] border px-3 py-2.5 transition',
                       'border-[#C8D9B5] bg-[#EAF3DE]' => ($item['status'] ?? '') === 'ok',
                       'border-[#E8D5A8] bg-[#FBF6EB]' => ($item['status'] ?? '') === 'warn',
                       'border-[#E5E3DA] bg-[#F1EFE8]' => ($item['status'] ?? '') === 'na',
                   ])>
                    <div class="text-xs font-medium text-[#2C2C2A]">{{ $item['label'] }}</div>
                    <div class="mt-0.5 text-[11px] text-[#5F5E5A]">{{ $item['detail'] }}</div>
                </a>
            @endforeach
        </div>
    </section>
@endif
