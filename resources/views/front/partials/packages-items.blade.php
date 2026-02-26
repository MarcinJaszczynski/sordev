@php
    // Use centralized helper to get region slug for links
    $regionSlugForLinks = \App\Support\Region::slugForLinks(isset($start_place_id) ? (int)$start_place_id : null);
@endphp
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
    <div class="item pb_25">
        <div class="package-box">
            <div class="package-box-layout">
                <div
                    class="package-box-photo"
                    style="background-image: url({{ asset('storage/' . ($item->featured_image ?? '')) }}); cursor: pointer;"
                    @php
                        $__baseUrl = route('package.pretty', [
                            'regionSlug' => $regionSlugForLinks,
                            'dayLength' => $item->duration_days . '-dniowe',
                            'id' => $item->id,
                            'slug' => $item->slug,
                        ]);
                    @endphp
                    onclick="window.location.href='{{ $__baseUrl }}';">
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
                                    @php $tUrl = route('packages', ['regionSlug' => $regionSlugForLinks]) . '?tag=' . \Illuminate\Support\Str::slug($tag->name); @endphp
                                    <a href="{{ $tUrl }}" class="badge-tag">{{ $tag->name }}</a>
                                @endforeach
                            </div>
                        @endif
                        @if(isset($transportTypeCollection) && $transportTypeCollection->count())
                            <div class="transport-type-icons transport-type-icons--mobile" aria-label="Środki transportu">
                                @foreach($transportTypeCollection as $transportType)
                                    @php
                                        $iconPath = $transportType->icon_path ? asset('storage/' . ltrim($transportType->icon_path, '/')) : null;
                                        $fallbackLabel = (string) \Illuminate\Support\Str::of($transportType->name ?? '')->trim()->substr(0, 2)->upper();
                                    @endphp
                                    <span class="transport-type-icon" title="{{ $transportType->name }}">
                                        @if($iconPath)
                                            <img src="{{ $iconPath }}" alt="{{ $transportType->name }}">
                                        @else
                                            <span class="transport-type-fallback">{{ $fallbackLabel }}</span>
                                        @endif
                                    </span>
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
                            <div class="package-box-time">
                                <i class="fas fa-clock"></i> {{ $item->duration_days }} dni
                            </div>
                        </div>
                        @if(isset($transportTypeCollection) && $transportTypeCollection->count())
                            <div class="transport-type-block">
                                <div class="transport-type-label">Transport:</div>
                                <div class="transport-type-icons" aria-label="Środki transportu">
                                    @foreach($transportTypeCollection as $transportType)
                                        @php
                                            $iconPath = $transportType->icon_path ? asset('storage/' . ltrim($transportType->icon_path, '/')) : null;
                                            $fallbackLabel = (string) \Illuminate\Support\Str::of($transportType->name ?? '')->trim()->substr(0, 2)->upper();
                                        @endphp
                                        <span class="transport-type-icon" title="{{ $transportType->name }}">
                                            @if($iconPath)
                                                <img src="{{ $iconPath }}" alt="{{ $transportType->name }}">
                                            @else
                                                <span class="transport-type-fallback">{{ $fallbackLabel }}</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <div class="package-box-positioning-graphic-info"></div>
                        <div class="package-box-graphic-info">
                            <div class="amenity-title">Tagi:</div>
                            <div class="package-box-tags">
                                @php
                                    if (!method_exists($item, 'tags')) {
                                        $loadedTags = collect();
                                    } else {
                                        if (!$item->relationLoaded('tags') || $item->tags === null) {
                                            try { $item->setRelation('tags', $item->tags()->get()); } catch (Throwable $e) { $item->setRelation('tags', collect()); }
                                        }
                                        $loadedTags = $item->tags;
                                    }
                                @endphp
                                @if ($loadedTags && $loadedTags->isNotEmpty())
                                    @php $tagsBase = route('packages', ['regionSlug' => $regionSlugForLinks]); @endphp
                                    @foreach ($loadedTags as $tag)
                                        @php $tSlug = \Illuminate\Support\Str::slug($tag->name); $tUrl = $tagsBase . '?tag=' . $tSlug; @endphp
                                        <a href="{{ $tUrl }}" class="badge-tag">{{ $tag->name }}</a>
                                    @endforeach
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
                                            $isPln = function($cur){
                                                if (!$cur) return false;
                                                $code = strtoupper(trim($cur->code ?? ''));
                                                $symbol = strtoupper(trim($cur->symbol ?? ''));
                                                $name = strtoupper(trim($cur->name ?? ''));
                                                return $code === 'PLN' || $symbol === 'PLN' || str_contains($name, 'ZŁOT');
                                            };
                                            $plnPrices = $validPrices->filter(function($p) use ($isPln){ return isset($p->currency) && $isPln($p->currency); });
                                            if ($plnPrices->count() > 0) {
                                                // Prefer controller computed_price if present
                                                if (isset($item->computed_price) && is_numeric($item->computed_price)) {
                                                    $displayPrice = (float) $item->computed_price;
                                                } else {
                                                    $displayPrice = (float)$plnPrices->min('price_per_person');
                                                }
                                            }
                                            $grouped = $validPrices->groupBy(function($p){
                                                $c = $p->currency ?? null; $label = $c?->code ?: ($c?->symbol ?: 'OTHER'); return $label ?: 'OTHER';
                                            });
                                            $orderedKeys = [];
                                            foreach ($grouped->keys() as $k) { if (strtoupper((string)$k) === 'EUR') { $orderedKeys[] = $k; } }
                                            foreach ($grouped->keys() as $k) { if (strtoupper((string)$k) !== 'EUR') { $orderedKeys[] = $k; } }
                                            foreach($orderedKeys as $code) {
                                                $group = $grouped->get($code);
                                                $sample = $group->first()->currency ?? null;
                                                if ($sample && $isPln($sample)) continue;
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
                                        // Preferuj computed_price wyliczoną w kontrolerze (lokalne ceny, PLN, najnowsze per qty)
                                        if(isset($item->computed_price) && is_numeric($item->computed_price)) {
                                            $displayPrice = (float) $item->computed_price;
                                        }
                                    @endphp
                                    @if(isset($displayPrice) && is_numeric($displayPrice))
                                        @php $displayPriceRounded = ceil($displayPrice / 5) * 5; @endphp
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
                                $qtyNote = null;
                                if (isset($requestedQty) && $requestedQty) {
                                    $qtyToPrice = [];
                                    if ($item->pricesPerPerson && $item->pricesPerPerson->count()) {
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
                                                if ($lower !== null) $closestQty = $lower; else $closestQty = $upper;
                                            }
                                            if (isset($closestQty) && isset($qtyToPrice[$closestQty])) {
                                                $qtyPrice = $qtyToPrice[$closestQty];
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endforeach