@php
    // Use centralized helper to get region slug for links
    $regionSlugForLinks = \App\Support\Region::slugForLinks(isset($start_place_id) ? (int)$start_place_id : null);
@endphp
<style>
@media (max-width: 966px) {
    .package-box-name-mobile .title-section { padding:8px 12px; }
    .package-box-name-mobile .title-section .title a { display:block; font-size:1.04rem; font-weight:700; color:#222 !important; text-decoration:none; line-height:1.2; }
    .package-box-name-mobile .title-section .title a:hover { text-decoration:underline; }
    .package-box-name-mobile .title-section .length { display:block; font-size:.85rem; color:#555; font-weight:400; margin:2px 0 6px; line-height:1.25; }
    .package-box-name-mobile .title-section .type { display:flex; flex-wrap:wrap; gap:6px; }
    .package-box-name-mobile .title-section .badge-tag { background:#f1f1f1; padding:4px 8px; border-radius:12px; font-size:.72rem; color:#333; text-decoration:none; font-weight:500; }
    .package-box-name-mobile .title-section .badge-tag:hover { background:#e0e0e0; }
}
.transport-type-block { margin:10px 0 6px; }
.transport-type-label { font-size:.85rem; font-weight:600; color:#4a4a4a; margin-bottom:4px; }
.transport-type-icons { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.transport-type-icon { width:38px; height:38px; border-radius:10px; background:#f6f6f6; border:1px solid #ececec; padding:4px; display:flex; align-items:center; justify-content:center; box-shadow:0 1px 2px rgba(0,0,0,0.05); }
.transport-type-icon img { max-width:100%; max-height:100%; object-fit:contain; }
.transport-type-fallback { font-size:.75rem; font-weight:600; color:#666; text-transform:uppercase; letter-spacing:.02em; }
.transport-type-icons--mobile { margin-top:6px; }

.package-box-layout { align-items: flex-start; }
.package-box-photo {
    width: 35% !important;
    flex: 0 0 35% !important;
    max-width: 35% !important;
    height: auto !important;
    aspect-ratio: 1 / 1 !important;
    max-height: none !important;
    align-self: flex-start !important;
    overflow: hidden !important;
}
.package-box-photo img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    object-position: center !important;
    display: block !important;
}
@media (max-width: 966px) {
    .package-box-photo {
        width: 100% !important;
        flex: 0 0 auto !important;
        max-width: 100% !important;
        height: auto !important;
        aspect-ratio: 1 / 1 !important;
    }
}
</style>
<!-- preserved original styles (no additional responsive overrides) -->
@php $hasAny = (isset($eventTemplate) && count($eventTemplate) > 0); @endphp
@if(!$hasAny)
    <div style="padding:40px 10px; text-align:center; color:#444; font-size:1rem; font-weight:500;">
        Brak ofert spełniających kryteria wyszukiwania.
    </div>
@endif

@foreach($eventTemplate as $item)
    @php
        if ($item->relationLoaded('transportTypes')) {
            $transportTypeCollection = $item->transportTypes;
        } elseif (method_exists($item, 'transportTypes')) {
            try {
                $transportTypeCollection = $item->transportTypes()->get();
            } catch (Throwable $e) {
                $transportTypeCollection = collect();
            }
        } else {
            $transportTypeCollection = collect();
        }
    @endphp
    <div class="item pb_25 package-item" data-id="{{ $item->id }}">
        <div class="package-box">
            <div class="package-box-layout">
                <div
                    class="package-box-photo"
                    style="cursor: pointer;"
                    @php
                        $__baseUrl = route('package.pretty', [
                            'regionSlug' => $regionSlugForLinks,
                            'dayLength' => $item->duration_days . '-dniowe',
                            'id' => $item->id,
                            'slug' => $item->slug,
                        ]);
                        // Jeśli mamy wybrane start_place_id -> dołączamy go do linku jako query param
                        if (isset($start_place_id) && $start_place_id) {
                            $__baseUrl = $__baseUrl . '?start_place_id=' . (int)$start_place_id;
                        }
                    @endphp
                    onclick="window.location.href='{{ $__baseUrl }}';">
                    <img src="{{ $item->preview_image_url ?: asset('uploads/default.png') }}" alt="{{ $item->name }}" style="width: 100%; height: 100%; object-fit: cover; object-position: center; display: block;" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='{{ $item->full_image_url ?: asset('uploads/default.png') }}';">
                </div>
                <div class="package-box-name-mobile">
                    <div class="title-section">
                        <div class="title"><a href="{{ $__baseUrl }}">{{ $item->name }}</a></div>
                        <div class="length">{{ $item->duration_days == 1 ? 'jednodniowa wycieczka szkolna' : $item->duration_days . '-dniowa wycieczka szkolna' }}</div>
                        @php
                            $mobileTags = [];
                            if ($item->relationLoaded('tags') && $item->tags) {
                                $mobileTags = $item->tags->take(3);
                            } else {
                                try { $mobileTags = $item->tags()->limit(3)->get(); } catch (Throwable $e) { $mobileTags = collect(); }
                            }
                        @endphp
                        @if($mobileTags && $mobileTags->count())
                            <div class="type">
                                @foreach($mobileTags as $tag)
                                    @php
                                        $tUrl = route('packages', ['regionSlug' => $regionSlugForLinks]) . '?tag=' . \Illuminate\Support\Str::slug($tag->name);
                                        if (isset($start_place_id) && $start_place_id) { $tUrl .= '&start_place_id=' . (int)$start_place_id; }
                                    @endphp
                                    <a href="{{ $tUrl }}" class="badge-tag">{{ $tag->name }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                <div class="package-box-info">
                    <div class="left">
                        <div class="package-box-name">
                            <a href="{{ route('package.pretty', [
                                    'regionSlug' => $regionSlugForLinks,
                                    'dayLength' => $item->duration_days . '-dniowe',
                                    'id' => $item->id,
                                    'slug' => $item->slug,
                                ]) }}">{{ $item->name }}</a>
                            @if($item->subtitle)
                                <div class="package-box-subtitle">{{ $item->subtitle }}</div>
                            @endif
                        </div>
                        <div class="package-box-small-info">
                            <div class="package-box-time-wrapper">
                                <div class="package-box-time" style="padding-bottom:3px">
                                    <i class="fas fa-clock"></i> {{ $item->duration_days }} dni
                                </div>
                            </div>
                            @if(isset($transportTypeCollection) && $transportTypeCollection->count())
                                <div class="package-box-transport-wrapper">
                                    <div class="package-box-time" style="padding-bottom:3px">
                                        @foreach($transportTypeCollection as $index => $transportType)
                                            @php
                                                $name = trim(strtolower($transportType->name ?? ''));
                                                $iconHtml = '<i class="fa-solid fa-train" style="margin-right:6px;"></i>';
                                                if (str_contains($name, 'autokar') || str_contains($name, 'autobus')) {
                                                    $iconHtml = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="1.2em" height="1.2em" style="margin-right:6px; vertical-align: -0.15em; fill: currentColor; display: inline-block;"><path d="M480 64C568.4 64 640 135.6 640 224L640 448C640 483.3 611.3 512 576 512L570.4 512C557.2 549.3 521.8 576 480 576C438.2 576 402.7 549.3 389.6 512L250.5 512C237.3 549.3 201.8 576 160.1 576C118.4 576 82.9 549.3 69.7 512L64 512C28.7 512 0 483.3 0 448L0 160C0 107 43 64 96 64L480 64zM160 432C133.5 432 112 453.5 112 480C112 506.5 133.5 528 160 528C186.5 528 208 506.5 208 480C208 453.5 186.5 432 160 432zM480 432C453.5 432 432 453.5 432 480C432 506.5 453.5 528 480 528C506.5 528 528 506.5 528 480C528 453.5 506.5 432 480 432zM480 128C462.3 128 448 142.3 448 160L448 352C448 369.7 462.3 384 480 384L544 384C561.7 384 576 369.7 576 352L576 224C576 171 533 128 480 128zM248 288L352 288C369.7 288 384 273.7 384 256L384 160C384 142.3 369.7 128 352 128L248 128L248 288zM96 128C78.3 128 64 142.3 64 160L64 256C64 273.7 78.3 288 96 288L200 288L200 128L96 128z"/></svg>';
                                                } elseif (str_contains($name, 'pociąg') || str_contains($name, 'pociag')) {
                                                    $iconHtml = '<i class="fa-solid fa-train" style="margin-right:6px;"></i>';
                                                } elseif (str_contains($name, 'samolot')) {
                                                    $iconHtml = '<i class="fa-solid fa-plane" style="margin-right:6px;"></i>';
                                                } elseif (str_contains($name, 'prom')) {
                                                    $iconHtml = '<i class="fa-solid fa-sailboat" style="margin-right:6px;"></i>';
                                                } elseif (str_contains($name, 'minibus')) {
                                                    $iconHtml = '<i class="fa-solid fa-van-shuttle" style="margin-right:6px;"></i>';
                                                }
                                            @endphp
                                            <span style="margin:0 3px 0 0; white-space:nowrap;">
                                                {!! $iconHtml !!}{{ $transportType->name }}
                                            </span>
                                            @if($index < $transportTypeCollection->count() - 1)
                                                <span style="margin:0 3px;">+</span>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="package-box-positioning-graphic-info"></div>
                        <div class="package-box-graphic-info">
                            <div class="amenity-title">Tagi:</div>

                            <div class="package-box-tags">
                                @php
                                    // Fallback: jeśli relacja 'tags' nie została załadowana, dociągnij ją (minimalnie) – chroni przed N+1 jeśli zwykle eager loaded.
                                    if (!method_exists($item, 'tags')) {
                                        $loadedTags = collect();
                                    } else {
                                        if (!$item->relationLoaded('tags') || $item->tags === null) {
                                            // Ostrożnie: pojedyncze zapytanie – akceptowalne jako fallback.
                                            $item->setRelation('tags', $item->tags()->get());
                                        }
                                        $loadedTags = $item->tags;
                                    }
                                @endphp
                                @if ($loadedTags && $loadedTags->isNotEmpty())
                                    @php $tagsBase = route('packages', ['regionSlug' => $regionSlugForLinks]); @endphp
                                    @foreach ($loadedTags as $tag)
                                            @php
                                                $tSlug = \Illuminate\Support\Str::slug($tag->name);
                                                $tUrl = $tagsBase . '?tag=' . $tSlug;
                                                if (isset($start_place_id) && $start_place_id) { $tUrl .= '&start_place_id=' . (int)$start_place_id; }
                                            @endphp
                                            <a href="{{ $tUrl }}" class="badge-tag">{{ $tag->name }}</a>
                                    @endforeach
                                @else
                                    {{-- Brak tagów --}}
                                @endif
                            </div>
                        </div>
                        </div>
                    <div class="right">
                        <div class="price-2-boxes">
                        <div class="package-box-actual-price">
                            @php
                                $displayPrice = null;
                                $otherCurrencyParts = [];

                                if ($item->pricesPerPerson && $item->pricesPerPerson->count()) {
                                    $validPrices = $item->pricesPerPerson->where('price_per_person', '>', 0);
                                    if ($validPrices->count() > 0) {
                                        // helper: wykryj PLN po symbol/nazwie (bez code, bo nie istnieje w tabeli)
                                        $isPln = function($cur){
                                            if (!$cur) return false;
                                            $symbol = strtoupper(trim($cur->symbol ?? ''));
                                            $name = strtoupper(trim($cur->name ?? ''));
                                            return $symbol === 'PLN' || str_contains($name, 'ZŁOT');
                                        };

                                        // Najpierw PLN: minimalna cena (już zaokrąglona przy zapisie) – bez ponownego ceil.
                                        $plnPrices = $validPrices->filter(function($p) use ($isPln){ return isset($p->currency) && $isPln($p->currency); });
                                            if ($plnPrices->count() > 0) {
                                                // Prefer controller computed_price if present
                                                if (isset($item->computed_price) && is_numeric($item->computed_price)) {
                                                    $displayPrice = (float) $item->computed_price;
                                                } else {
                                                    $displayPrice = (float)$plnPrices->min('price_per_person');
                                                }
                                            }

                                        // Inne waluty: grupuj po etykiecie i bierz min (pełne jednostki)
                                        $grouped = $validPrices->groupBy(function($p){
                                            $c = $p->currency ?? null;
                                            $label = $c?->symbol ?: 'OTHER'; // użyj symbol zamiast code
                                            return $label ?: 'OTHER';
                                        });
                                        // Prefer EUR first, then the rest (PLN skipped below)
                                        $orderedKeys = [];
                                        foreach ($grouped->keys() as $k) { if (strtoupper((string)$k) === 'EUR') { $orderedKeys[] = $k; } }
                                        foreach ($grouped->keys() as $k) { if (strtoupper((string)$k) !== 'EUR') { $orderedKeys[] = $k; } }
                                        foreach($orderedKeys as $code) {
                                            $group = $grouped->get($code);
                                            $sample = $group->first()->currency ?? null;
                                            if ($sample && $isPln($sample)) continue; // pomiń PLN w liniach dodatkowych
                                            $min = $group->min('price_per_person');
                                            if ($min && $min > 0) {
                                                $amt = ceil($min);
                                                $label = $sample?->code ?: ($sample?->symbol ?: $code);
                                                $otherCurrencyParts[] = $amt . ' ' . $label;
                                            }
                                        }
                                    }
                                }
                            @endphp
                            <div class="price-multiline">
                                @php
                                    // Jeśli kontroler wyliczył computed_price (lokalna preferencja), to zawsze użyj go jako źródła ceny na liście.
                                    if(isset($item->computed_price) && is_numeric($item->computed_price)) {
                                        $displayPrice = $item->computed_price;
                                    }
                                @endphp
                                @if(isset($displayPrice))
                                    @php
                                        // Zaokrąglij do pełnych 5 zł tak jak na stronie szczegółu (ceil do najbliższych 5)
                                        $displayPriceRounded = ceil($displayPrice / 5) * 5;
                                    @endphp
                                    <div>od <b>{{ number_format($displayPriceRounded, 0, ',', ' ') }} zł</b></div>
                                @else
                                    <div><b>Cena w przygotowaniu</b></div>
                                @endif
                                @if(!empty($otherCurrencyParts))
                                    @foreach($otherCurrencyParts as $part)
                                        <div>+ {{ $part }}</div>
                                    @endforeach
                                @endif
                                <div class="price-note">za osobę</div>
                            </div>
                        </div>
                        @php
                            // Dodatkowo: pokaż cenę dla zadanej liczby osób (requestedQty), jeśli policzona/computed_price bazuje na najbliższym progu
                            $qtyNote = null;
                            if (isset($requestedQty) && $requestedQty) {
                                // spróbuj znaleźć najbliższy próg i cenę dla niego
                                $qtyToPrice = [];
                                if ($item->pricesPerPerson && $item->pricesPerPerson->count()) {
                                    // grupy per qty (najnowsza po id)
                                    $grouped = $item->pricesPerPerson
                                        ->where('price_per_person', '>', 0)
                                        ->groupBy('event_template_qty_id')
                                        ->map(function($group){ return $group->sortByDesc('id')->first(); })
                                        ->values();
                                    foreach ($grouped as $price) {
                                        $q = optional($price->eventTemplateQty)->qty;
                                        if ($q) $qtyToPrice[(int)$q] = (float) $price->price_per_person;
                                    }
                                    ksort($qtyToPrice);
                                    if (!empty($qtyToPrice)) {
                                        if (isset($qtyToPrice[$requestedQty])) {
                                            $closestQty = $requestedQty;
                                        } else {
                                            $lower = null; $upper = null;
                                            foreach (array_keys($qtyToPrice) as $q) {
                                                if ($q < $requestedQty) $lower = $q;
                                                if ($q > $requestedQty) { $upper = $q; break; }
                                            }
                                            // Preferuj mniejszy próg (lower). Gdy brak mniejszego – bierz najmniejszy większy (upper)
                                            if ($lower !== null) $closestQty = $lower;
                                            else $closestQty = $upper; // może pozostać null, gdy brak danych
                                        }
                                        if (isset($closestQty) && isset($qtyToPrice[$closestQty])) {
                                            $qtyPrice = $qtyToPrice[$closestQty]; // już zaokrąglone w bazie
                                            $qtyNote = '(' . $qtyPrice . ' zł/os. dla grupy ' . $closestQty . ' osób)';
                                        }
                                    }
                                }
                            }
                        @endphp
                                @if($qtyNote)
                                    <div style="margin-top:4px; font-size: 12px; color:#666;">{{ $qtyNote }}</div>
                                @endif

                                <div class="package-box-price">
                                    <a href="{{ route('package.pretty', [
                                            'regionSlug' => $regionSlugForLinks,
                                            'dayLength' => $item->duration_days . '-dniowe',
                                            'id' => $item->id,
                                            'slug' => $item->slug,
                                        ]) }}">Pokaż ofertę</a>
                                </div>
                            </div> <!-- .price-2-boxes -->
                        </div> <!-- .right -->
                    </div> <!-- .package-box-info -->
                </div> <!-- .package-box-layout -->
            </div> <!-- .package-box -->
        </div> <!-- .item -->
@endforeach

{{-- Pagination --}}
@if(isset($eventTemplate) && $eventTemplate instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $eventTemplate->hasPages())
    <noscript>
        <div class="pagination-container" style="text-align: center; margin-top: 40px; margin-bottom: 6px;">
            {{ $eventTemplate->appends(request()->query())->links('pagination::bootstrap-4') }}
        </div>
        <div class="pagination-info" style="text-align: center; color:#666; font-size: 0.9em; margin-bottom: 20px;">
            Wyświetlanie {{ $eventTemplate->firstItem() }}–{{ $eventTemplate->lastItem() }} z {{ $eventTemplate->total() }} wyników
        </div>
    </noscript>
@endif
