@extends('front.layout.master')

@section('head')
    @include('front.partials.seo')
@endsection

@section('main_content')

@php
    // Ensure start place and region id are always available for links (request -> cookie -> default)
    $startPlaceId = request('start_place_id') ?? request()->cookie('start_place_id') ?? 16;
    $regionIdForLink = request('region_id') ?? request()->cookie('region_id') ?? ($region_id ?? 16);
    // Normalizacja: kontroler przekazuje `start_place_id` jako zmienną widoku. Dla spójności używamy
    // `$currentStartPlaceId` w całym pliku (wcześniej występowały mieszane nazwy i to powodowało
    // błędne filtrowanie price rows w widoku).
    $currentStartPlaceId = $start_place_id ?? $startPlaceId ?? null;
@endphp

    <div class="page-top">
        <div class="container">
            <div class="breadcrumb-container">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{route('home')}}">Start</a></li>
                    <li class="breadcrumb-item active">Przegląd polecanych wycieczek</li>
                </ol>
            </div>
        </div>
    </div>
    <div class="package pt_20">
    <!-- inline centering overrides removed; main CSS will control layout -->
    <div class="region-information">
        <div class="text">
            Pokaż ofertę dla:
            @php use Illuminate\Support\Str; @endphp
            <form name="regionForm" id="regionForm" action="/{{ $current_region_slug ?? 'region' }}/directory-packages" method="get">
                <div class="select-form-div">
                    <select name="start_place_id" id="start_place_id_top" class="form-select-region-information">
                        @foreach(($startPlaces ?? collect()) as $place)
                            @php $slug = Str::slug($place->name); @endphp
                            <option value="{{ $place->id }}" data-slug="{{ $slug }}" @if((int)($current_start_place_id ?? $currentStartPlaceId ?? 0) === (int)$place->id) selected @endif>
                                {{ $place->name }} i okolice
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary" style="margin-left:8px;">Filtruj</button>
                </div>
            </form>

            <div class="icon"><i class="fas fa-info-circle"></i>
                <div class="explanation">
                    Prosimy o wybranie miasta opowiadającego miejscu wyjazdu lub miasta, które znajduje się najbliżej.
                </div>
            </div>
        </div>
    </div></div>
<script>
// Spójne zachowanie: brak auto-redirectu, tylko po kliknięciu "Filtruj".
document.addEventListener('DOMContentLoaded', function(){
    var form = document.getElementById('regionForm');
    var select = document.getElementById('start_place_id_top');
    if(form && select){
        form.addEventListener('submit', function(e){
            // Zapis cookie
            var id = select.value;
            var opt = select.options[select.selectedIndex];
            var slug = opt ? (opt.getAttribute('data-slug') || 'region') : 'region';
            var date = new Date();
            date.setTime(date.getTime() + (30*24*60*60*1000));
            document.cookie = 'start_place_id=' + id + ';path=/;expires=' + date.toUTCString();
            // Przepisz action na poprawny slug jeśli zmieniono
            var base = '/' + slug + '/directory-packages';
            form.action = base;
        });
    }
});
</script>
<div class="directory-buttons pt_10 pb_15">
    <div class="container">
        <!-- <div class="region-information">
            <div class="text">
                Pokaż ofertę dla:
                <script>
                    function autoSubmit() {
                        document.getElementById("regionForm").submit();
                    }
                </script>
                <form name="regionForm" id="regionForm" action="{{ route('packages') }}" method="get">
                    <div class="select-form-div">
                        <select name="region_id" class="form-select-region-information" oninput="autoSubmit()">
                            <option selected value="16">Widok domyślny (Warszawa)</option>
                            @foreach($regions as $region)
                                <option value="{{ $region->id }}" @if($region_id == $region->id) selected @endif>{{ $region->name }} i okolice</option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <div class="icon"><i class="fas fa-info-circle"></i>
                    <div class="explanation">
                        Prosimy o wybranie miasta opowiadającego miejscu wyjazdu lub miasta, które znajduje się najbliżej.
                    </div>
                </div>
            </div>
        </div>

        <div class="directory-bar">
            <div class="region-bar">
                <div class="box-region">
                <div class="text">
                    <div class="h2 directory-navigation">Pokaż ofertę dla regionu:</div>
                    <script>
                        function autoSubmit() {
                            document.getElementById("regionForm").submit();
                        }
                    </script>
                    <div class="destination">
                        <form class="destination_search" action="{{ route('packages') }}" method="get" style="min-width: 80% !important;">
                            <div class="layout">
                                <div class="mobile-destination-from">
                                    <div class="destination_from">
                                        <div class="destination_from_select_option">
                                            <select name="region_id" class="destination_from_select_form" required>
                                                <option class="where_from" value="" disabled selected>Skąd? *</option>
                                                @foreach($regions as $region)
                                                    <option value="{{ $region->id }}"
                                                            @if($region_id == $region->id) selected @endif>
                                                        {{ $region->name }} i okolice
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="destination_from_search">
                                        </div>
                                    </div>
                                    <div class="icon"><i class="fas fa-info-circle"></i>
                                        <div class="explanation">
                                            Prosimy o wybranie miasta opowiadającego miejscu wyjazdu lub miasta, które znajduje się najbliżej.
                                        </div>
                                    </div></div>
                                <button class="destination_search_button" type="submit">Szukaj</button>
                            </div>
                        </form>
                    </div></div> -->
                    @php
                        $lengthButtonsCurrentId = (int) ($current_start_place_id ?? $currentStartPlaceId ?? 0);
                        $lengthButtonsPlace = ($startPlaces ?? collect())->firstWhere('id', $lengthButtonsCurrentId);
                        $lengthButtonsSlug = $lengthButtonsPlace ? \Illuminate\Support\Str::slug($lengthButtonsPlace->name) : ($current_region_slug ?? 'region');
                        $lengthButtonQueryDefaults = [
                            'sort_by' => request('sort_by', 'name_asc'),
                            'destination_name' => request('destination_name', ''),
                        ];

                        if ($lengthButtonsPlace) {
                            $lengthButtonQueryDefaults['start_place_id'] = $lengthButtonsPlace->id;
                        } elseif ($lengthButtonsCurrentId) {
                            $lengthButtonQueryDefaults['start_place_id'] = $lengthButtonsCurrentId;
                        }

                        $lengthButtonUrl = function (string $lengthValue) use ($lengthButtonsSlug, $lengthButtonQueryDefaults) {
                            $params = array_filter(
                                array_merge(['regionSlug' => $lengthButtonsSlug, 'length_id' => $lengthValue], $lengthButtonQueryDefaults),
                                function ($value) {
                                    return $value !== null;
                                }
                            );
                            if (!array_key_exists('destination_name', $params)) {
                                $params['destination_name'] = '';
                            }
                            return route('packages', $params);
                        };
                    @endphp
                    <div class="h2 directory-navigation">Przejdź do pełnej oferty wycieczek o długości...</div>
                    <div class="length-buttons">
                        <a href="{{ $lengthButtonUrl('1') }}"><button>1 dzień</button></a>
                        <a href="{{ $lengthButtonUrl('2') }}"><button>2 dni</button></a>
                        <a href="{{ $lengthButtonUrl('3') }}"><button>3 dni</button></a>
                        <a href="{{ $lengthButtonUrl('4') }}"><button>4 dni</button></a>
                        <a href="{{ $lengthButtonUrl('5') }}"><button>5 dni</button></a>
                        <a href="{{ $lengthButtonUrl('6plus') }}"><button>6 i więcej dni</button></a>
                    </div>

                    <!-- Search Form (identical to home) -->
                    <div class="container package-page-layout package-page-border pt_80">
                        <div class="destination">
                            <h2 class="destination_question">Wyszukaj swoją wycieczkę szkolną!</h2>
                            <form class="destination_search" action="{{ route('packages') }}" method="get">
                                <div class="layout">
                                    <div class="mobile-destination-from">
                                        <div class="destination_from">
                                            <div class="destination_from_select_option">
                                                <select name="start_place_id" id="start_place_id_select" class="destination_from_select_form" required onchange="saveStartPlaceId(this.value)">
                                                    <option class="where_from" id="where_from_option" value="" disabled>Skąd? *</option>
                                                    @if(isset($startPlaces))
                                                        @foreach($startPlaces as $place)
                                                            <option value="{{ $place->id }}">{{ $place->name }} i okolice</option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                            </div>
                                            <div class="destination_from_search">
                                            </div>
                                        </div>
                                        <div class="icon"><i class="fas fa-info-circle"></i>
                                            <div class="explanation">
                                                Prosimy o wybranie miasta opowiadającego miejscu wyjazdu lub miasta, które znajduje się najbliżej.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mobile-destination-length">
                                        <div class="destination_length_select_option">
                                            <select name="length_id" class="destination_length_select_form">
                                                <option value="">Wszystkie długości</option>
                                                <option value="1" @if(request('length_id') == '1') selected @endif>1 dzień</option>
                                                <option value="2" @if(request('length_id') == '2') selected @endif>2 dni</option>
                                                <option value="3" @if(request('length_id') == '3') selected @endif>3 dni</option>
                                                <option value="4" @if(request('length_id') == '4') selected @endif>4 dni</option>
                                                <option value="5" @if(request('length_id') == '5') selected @endif>5 dni</option>
                                                <option value="6plus" @if(request('length_id') == '6plus') selected @endif>6 dni i więcej</option>
                                            </select>
                                            <div class="destination_from_search">
                                            </div>
                                        </div>
                                        <div class="icon"><i class="fas fa-info-circle"></i>
                                            <div class="explanation">
                                                Wyszukaj wycieczki o wszystkich możliwych długościach lub wybierz konkretną ilość dni.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mobile-destination-length">
                                        <div class="destination_length_select_option">
                                            <select name="sort_by" class="destination_length_select_form">
                                                <option value="price_asc" @if(request('sort_by') == 'price_asc') selected @endif>Cena: od najniższej</option>
                                                <option value="price_desc" @if(request('sort_by') == 'price_desc') selected @endif>Cena: od najwyższej</option>
                                                <option value="name_asc" @if(request('sort_by', 'name_asc') == 'name_asc') selected @endif>Alfabetycznie A-Z</option>
                                                <option value="name_desc" @if(request('sort_by') == 'name_desc') selected @endif>Alfabetycznie Z-A</option>
                                                <option value="duration_asc" @if(request('sort_by') == 'duration_asc') selected @endif>Czas: rosnąco</option>
                                                <option value="duration_desc" @if(request('sort_by') == 'duration_desc') selected @endif>Czas: malejąco</option>
                                            </select>
                                            <div class="destination_from_search"></div>
                                        </div>
                                    </div>
                                    <div class="mobile-question-where">
                                        <div class="destination_where_ask_frame">
                                            <input type="text" name="destination_name" class="form-control destination_where_ask" placeholder="Dokąd?" value="{{ request('destination_name', '') }}">
                                        </div>
                                        <div class="icon"><i class="fas fa-info-circle"></i>
                                            <div class="explanation">
                                               Wpisanie kierunek wycieczki zawęzi wyniki tylko do tej destynacji. Pozostaw to pole puste, by zobaczyć wszystkie dostępne wyjazdy.
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="destination_search_button">Szukaj</button>
                                </div>
                            </form>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    var select = document.getElementById('start_place_id_select');
                                    var whereFromOption = document.getElementById('where_from_option');
                                    var cookieVal = (function(){
                                        var nameEQ = 'start_place_id=';
                                        var ca = document.cookie.split(';');
                                        for(var i=0;i < ca.length;i++) {
                                            var c = ca[i];
                                            while (c.charAt(0)==' ') c = c.substring(1,c.length);
                                            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
                                        }
                                        return null;
                                    })();
                                    if (cookieVal && select) {
                                        select.value = cookieVal;
                                        if (whereFromOption) whereFromOption.style.display = 'none';
                                    } else {
                                        if (whereFromOption) whereFromOption.style.display = '';
                                        if (select) select.value = '';
                                    }
                                });
                                function saveStartPlaceId(val) {
                                    var date = new Date();
                                    date.setTime(date.getTime() + (30*24*60*60*1000));
                                    document.cookie = 'start_place_id=' + val + ';path=/;expires=' + date.toUTCString();
                                }
                            </script>
                        </div>
                    </div>

                    <style>
                        .search-form {
                            animation: fadeInUp 0.5s ease-out;
                        }

                        .search-input:focus, .filter-select:focus {
                            border-color: #007bff;
                            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
                        }

                        .search-button:hover {
                            background-color: #0056b3;
                            transform: translateY(-1px);
                        }

                        .reset-button:hover {
                            background-color: #545b62;
                        }

                        .clear-search:hover {
                            color: #666;
                        }

                        @keyframes fadeInUp {
                            from {
                                opacity: 0;
                                transform: translateY(20px);
                            }
                            to {
                                opacity: 1;
                                transform: translateY(0);
                            }
                        }

                        @media (max-width: 768px) {
                            .search-form {
                                padding: 20px;
                            }

                            .search-main-row, .search-filters-row {
                                flex-direction: column;
                                gap: 15px;
                            }

                            .search-input-group, .filter-group {
                                min-width: auto;
                                width: 100%;
                            }

                            .search-button {
                                width: 100%;
                                padding: 15px;
                            }

                            .filter-group {
                                display: flex;
                                flex-direction: column;
                                align-items: stretch;
                            }

                            .filter-select {
                                margin-top: 5px;
                            }
                        }

                        @media (max-width: 480px) {
                            .search-container {
                                margin: 20px 0;
                                padding: 0 15px;
                            }

                            .search-form {
                                padding: 15px;
                            }

                            .search-input, .filter-select {
                                font-size: 16px; /* Prevents zoom on iOS */
                            }
                        }
                    </style>

                    <script>
                        function clearSearch() {
                            const input = document.querySelector('.search-input');
                            const clearBtn = document.querySelector('.clear-search');
                            input.value = '';
                            clearBtn.style.display = 'none';
                            input.focus();
                        }

                        function resetFilters() {
                            const form = document.querySelector('.search-form');
                            form.reset();
                            // Clear URL parameters
                            const url = new URL(window.location);
                            url.searchParams.delete('destination_name');
                            url.searchParams.delete('sort_by');
                            url.searchParams.delete('event_type_id');
                            window.location.href = url.toString();
                        }

                        document.addEventListener('DOMContentLoaded', function() {
                            const searchInput = document.querySelector('.search-input');
                            const clearBtn = document.querySelector('.clear-search');

                            if (searchInput && clearBtn) {
                                searchInput.addEventListener('input', function() {
                                    clearBtn.style.display = this.value ? 'block' : 'none';
                                });
                            }

                            // Handle form submission with loading state
                            const searchForm = document.querySelector('.search-form');
                            if (searchForm) {
                                searchForm.addEventListener('submit', function(e) {
                                    const button = this.querySelector('.search-button');
                                    const originalText = button.innerHTML;
                                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>Szukam...';
                                    button.disabled = true;

                                    // Re-enable after 2 seconds if no redirect happens
                                    setTimeout(function() {
                                        button.innerHTML = originalText;
                                        button.disabled = false;
                                    }, 2000);
                                });
                            }

                            // Auto-submit on filter change (optional enhancement)
                            const filterSelects = document.querySelectorAll('.filter-select');
                            filterSelects.forEach(select => {
                                select.addEventListener('change', function() {
                                    // Optional: auto-submit on filter change
                                    // searchForm.submit();
                                });
                            });
                        });
                    </script>

            </div>
    </div></div>
</div>

{{-- Wyświetl wyniki wyszukiwania jeśli są dostępne --}}
@if(request('destination_name') || request('sort_by') || request('event_type_id'))
    <div class="package pt_20 pb_50">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="top-text" style="margin-bottom: 30px;">
                        <div class="h2">Wyniki wyszukiwania</div>
                        @if(request('destination_name'))
                            <p style="margin-top: 10px; color: #666;">Szukane hasło: <strong>{{ request('destination_name') }}</strong></p>
                        @endif
                    </div>

                    @if($packages && $packages->count() > 0)
                        @php
                            $startPlaceId = request('start_place_id') ?? request()->cookie('start_place_id') ?? 16;
                            $regionIdForLink = request()->region_id ?? request()->cookie('region_id', 16);
                        @endphp
                        <style>
                            /* Jednorazowy (scoped) styl mobilny dla tytułu listy ofert */
                            @media (max-width: 966px) {
                                .package-box-name-mobile .title-section { padding:8px 12px; }
                                .package-box-name-mobile .title-section .title { margin:0 0 2px 0; }
                                .package-box-name-mobile .title-section .title a { display:block; font-size:1.04rem; font-weight:700; color:#222 !important; text-decoration:none; line-height:1.2; }
                                .package-box-name-mobile .title-section .title a:hover { text-decoration:underline; }
                                .package-box-name-mobile .title-section .length { display:block; font-size:.85rem; color:#555; font-weight:400; margin:2px 0 6px; line-height:1.25; }
                                .package-box-name-mobile .title-section .type { display:flex; flex-wrap:wrap; gap:6px; }
                                .package-box-name-mobile .title-section .badge-tag { background:#f1f1f1; padding:4px 8px; border-radius:12px; font-size:.72rem; color:#333; text-decoration:none; font-weight:500; }
                                .package-box-name-mobile .title-section .badge-tag:hover { background:#e0e0e0; }
                            }
                        </style>
                        <div id="packages-results">
                            @foreach($packages as $item)
                                <div class="item pb_25">
                                    <div class="package-box">
                                        <div class="package-box-layout">
                                            <div
                                                class="package-box-photo"
                                                style="background-image: url({{ asset('storage/' . ($item->featured_image ?? '')) }}); cursor: pointer;"
                                                onclick="window.location.href='{{ $item->prettyUrl() }}';">
                                            </div>
                                            <div class="package-box-name-mobile">
                                                <div class="title-section">
                                                    <div class="title">
                                                        <a href="{{ $item->prettyUrl() }}">{{ $item->name }}</a>
                                                    </div>
                                                    <div class="length">
                                                        {{ $item->duration_days == 1 ? 'jednodniowa wycieczka szkolna' : $item->duration_days . '-dniowa wycieczka szkolna' }}
                                                    </div>
                                                    <div class="type">
                                                        @if($item->tags && $item->tags->count() > 0)
                                                            @foreach($item->tags->take(3) as $tag)
                                                                <a href="{{ url('/oferty?tag=' . $tag->slug) }}" class="badge badge-tag">{{ $tag->name }}</a>
                                                            @endforeach
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="package-box-info">
                                                <div class="left">
                                                    <div class="package-box-name">
                                                        <a href="{{ $item->prettyUrl() }}">{{ $item->name }}</a>
                                                        <div class="package-box-subtitle">{{ $item->duration_days == 1 ? 'jednodniowa' : $item->duration_days . '-dniowa' }} wycieczka szkolna</div>
                                                    </div>
                                                    <div class="package-box-small-info">
                                                        <div class="package-box-time">
                                                            <i class="fas fa-clock"></i> {{ $item->duration_days == 1 ? '1 dzień' : $item->duration_days . ' dni' }}
                                                        </div>
                                                    </div>
                                                    <div class="package-box-positioning-graphic-info"></div>
                                                    <div class="package-box-graphic-info">
                                                        @if($item->tags && $item->tags->count() > 0)
                                                            <div class="amenity-title">Tagi:</div>
                                                            <div class="package-box-tags">
                                                                @foreach($item->tags->take(3) as $tag)
                                                                    <a href="{{ url('/oferty?tag=' . $tag->slug) }}" class="badge-tag">{{ $tag->name }}</a>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="right">
                                                    <div class="price-2-boxes">
                                                        <div class="package-box-actual-price">
                                                            @php
                                                                $displayPrice = null;
                                                                $otherCurrencyParts = [];
                                                                // Preferuj gotowe pole price/computed_price (PLN, już 5-zł rounding).
                                                                // Jeśli kontroler ustawił `computed_price`, nie nadpisujemy go lokalnymi obliczeniami.
                                                                if (isset($item->computed_price) && is_numeric($item->computed_price)) {
                                                                    // Zaokrąglij do pełnych 5 zł tak jak na stronie szczegółu
                                                                    $displayPrice = (int) (ceil($item->computed_price / 5) * 5);
                                                                } elseif (isset($item->price) && is_numeric($item->price)) {
                                                                    $displayPrice = (int)$item->price;
                                                                }
                                                                if ($item->pricesPerPerson && $item->pricesPerPerson->count()) {
                                                                    $validPrices = $item->pricesPerPerson->where('price_per_person', '>', 0);
                                                                    // Tylko dla bieżącego start place (jak w controllerze)
                                                                    if (isset($currentStartPlaceId) && $currentStartPlaceId) {
                                                                        $validPrices = $validPrices->filter(function($p) use ($currentStartPlaceId) {
                                                                            return (int)$p->start_place_id === (int)$currentStartPlaceId;
                                                                        });
                                                                    } else {
                                                                        $validPrices = $validPrices->filter(function($p){ return $p->start_place_id === null; });
                                                                    }
                                                                    if ($validPrices->count() > 0) {
                                                                        // PLN wykrywany po symbolu/nazwie; min i zaokrąglenie do 5 zł w górę
                                                                        $isPln = function($c){
                                                                            if (!$c) return false;
                                                                            $symbol = strtoupper(trim($c->symbol ?? ''));
                                                                            $name = strtoupper(trim($c->name ?? ''));
                                                                            return $symbol === 'PLN' || str_contains($name, 'ZŁOT');
                                                                        };
                                                                        $plnPrices = $validPrices->filter(function($p) use ($isPln){ return isset($p->currency) && $isPln($p->currency); });
                                                                        if ($plnPrices->count() > 0) {
                                                                            // Preferuj computed_price ustawione w kontrolerze (już zaokrąglone na potrzeby wyświetlenia)
                                                                            if (isset($item->computed_price) && is_numeric($item->computed_price)) {
                                                                                if (!isset($displayPrice)) {
                                                                                    $displayPrice = (int) (ceil($item->computed_price / 5) * 5);
                                                                                }
                                                                            } else {
                                                                                if (!isset($displayPrice)) {
                                                                                    if (isset($item->computed_price) && is_numeric($item->computed_price)) {
                                                                                        $displayPrice = (int) (ceil($item->computed_price / 5) * 5);
                                                                                    } else {
                                                                                        if (isset($item->computed_price) && is_numeric($item->computed_price)) {
                                                                                            $displayPrice = (int) (ceil($item->computed_price / 5) * 5);
                                                                                        } else {
                                                                                            if (isset($item->computed_price) && is_numeric($item->computed_price)) {
                                                                                                $displayPrice = (int) (ceil($item->computed_price / 5) * 5);
                                                                                            } else {
                                                                                                $displayPrice = ceil($plnPrices->min('price_per_person') / 5) * 5;
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                }
                                                                            }
                                                                        }
                                                                        // Składniki walutowe: grupuj i bierz min, zaokrąglij do pełnych jednostek
                                                                        $grouped = $validPrices->groupBy(function($p){
                                                                            $c = $p->currency ?? null;
                                                                            return $c?->symbol ?: 'OTHER';
                                                                        });
                                                                        // Sort keys: EUR first, then others
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
                                                                                $label = $sample?->symbol ?: $code;
                                                                                $otherCurrencyParts[] = $amt . ' ' . $label;
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            @endphp
                                                            @if($displayPrice)
                                                                <div class="price-multiline">
                                                                    <div>od <b>{{ $displayPrice }} zł</b></div>
                                                                    @if(!empty($otherCurrencyParts))
                                                                        @foreach($otherCurrencyParts as $part)
                                                                            <div>+ {{ $part }}</div>
                                                                        @endforeach
                                                                    @endif
                                                                    <div class="price-note">za osobę</div>
                                                                </div>
                                                            @else
                                                                <b>cena do ustalenia</b>
                                                            @endif
                                                        </div>
                                                        <div class="package-box-price">
                                                            <a href="{{ $item->prettyUrl() }}">Pokaż ofertę</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Pagination --}}
                        @if($packages->hasPages())
                            <div class="pagination-container" style="text-align: center; margin-top: 40px; margin-bottom: 6px;">
                                {{ $packages->appends(request()->query())->links('pagination::bootstrap-4') }}
                            </div>
                            <div class="pagination-info" style="text-align: center; color:#666; font-size: 0.9em; margin-bottom: 20px;">
                                Wyświetlanie {{ $packages->firstItem() }}–{{ $packages->lastItem() }} z {{ $packages->total() }} wyników
                            </div>
                        @endif
                    @else
                        <div style="text-align: center; padding: 40px; width: 100%;">
                            <i class="fas fa-search" style="font-size: 48px; color: #ccc; margin-bottom: 20px;"></i>
                            <h3>Brak wyników wyszukiwania</h3>
                            <p style="color: #666; margin-top: 10px;">Nie znaleziono wycieczek spełniających podane kryteria.</p>
                            <a href="{{ route('directory-packages') }}" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">Wyczyść wyszukiwanie</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@else
    {{-- Standardowe sekcje z pakietami pogrupowanymi według długości --}}
    <div class="directory-buttons days">
        <div class="container pt_25 pb_15">
            <div class="package-directory-box">
                <div class="top-text">
                    <div class="h2">Wycieczki 1-dniowe</div>
                </div>
            <div class="layout">
                            @foreach($random_one_day as $item)
            <div class="package-preview">
                <div class="photo" style="background-image:url({{ asset('storage/' . ($item->featured_image ?? '')) }})"></div>
                <div class="text-window">
                    <div class="title"><a href="{{ $item->prettyUrl() }}">{{ $item->name }}</a></div>
                    <div class="price">
                            @php
                            // Use same logic as home method: filter by currentStartPlaceId
                            $displayPrice = null;
                            $otherCurrencyParts = [];
                            $currentStartPlaceId = $currentStartPlaceId ?? null;
                            // Prefer controller-provided computed_price
                            if (isset($item->computed_price) && is_numeric($item->computed_price)) {
                                $displayPrice = (int) (ceil($item->computed_price / 5) * 5);
                            }
                            
                            if ($item->pricesPerPerson && $item->pricesPerPerson->count()) {
                                $validPrices = $item->pricesPerPerson->where('price_per_person', '>', 0);
                                
                                // Filter by current start_place_id
                                $filteredForPlace = $validPrices->filter(function($p) use ($currentStartPlaceId) {
                                    if ($currentStartPlaceId) {
                                        return (int)$p->start_place_id === (int)$currentStartPlaceId;
                                    }
                                    return $p->start_place_id === null;
                                });
                                
                                if ($filteredForPlace->count() > 0) {
                                    $plnPrices = $filteredForPlace->filter(function($p){
                                        return isset($p->currency) && (
                                            (isset($p->currency->symbol) && $p->currency->symbol === 'PLN') ||
                                            (isset($p->currency->name) && str_contains(strtolower($p->currency->name), 'złot'))
                                        );
                                    });
                                    if ($plnPrices->count() > 0) {
                                        if (!isset($displayPrice)) {
                                            if (isset($item->computed_price) && is_numeric($item->computed_price)) {
                                                $displayPrice = (int) (ceil($item->computed_price / 5) * 5);
                                            } else {
                                                $displayPrice = ceil($plnPrices->min('price_per_person') / 5) * 5;
                                            }
                                        }
                                    }
                                    
                                    $grouped = $filteredForPlace->groupBy(function($p){
                                        return isset($p->currency) && isset($p->currency->symbol) ? $p->currency->symbol : 'OTHER';
                                    });
                                    $orderedKeys = [];
                                    foreach ($grouped->keys() as $k) { if (strtoupper((string)$k) === 'EUR') { $orderedKeys[] = $k; } }
                                    foreach ($grouped->keys() as $k) { if (strtoupper((string)$k) !== 'EUR') { $orderedKeys[] = $k; } }
                                    foreach($orderedKeys as $code) {
                                        $group = $grouped->get($code);
                                        if ($code === 'PLN') continue;
                                        $min = $group->min('price_per_person');
                                        if ($min && $min > 0) {
                                            $amt = ceil($min);
                                            $currencySample = $group->first()->currency ?? null;
                                            $label = $currencySample->symbol ?? $code;
                                            $otherCurrencyParts[] = $amt . ' ' . $label;
                                        }
                                    }
                                }
                            }
                        @endphp
                        @if($displayPrice)
                            od <b>{{ $displayPrice }} zł</b>@if(!empty($otherCurrencyParts)) + {{ implode(' + ', $otherCurrencyParts) }}@endif /os.
                        @else
                            <b>cena do ustalenia</b>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
                    <a class="check-all" href="{{ $lengthButtonUrl('1') }}">Zobacz wszystkie wycieczki 1-dniowe</a>
            </div>
        </div>
        </div>
        <div class="container pt_15 pb_15">
            <div class="package-directory-box">
            <div class="top-text">
                <div class="h2">Wycieczki 2-dniowe</div>
            </div>
            <div class="layout">
                                    @foreach($random_two_day as $item)
                    <div class="package-preview">
                        <div class="photo" style="background-image:url({{ asset('storage/' . ($item->featured_image ?? '')) }})"></div>
                        <div class="text-window">
                            <div class="title"><a href="{{ $item->prettyUrl() }}">{{ $item->name }}</a></div>
                            <div class="price">
                                        @php
                                            $displayPrice = null;
                                            $otherCurrencyParts = [];
                                            // Prefer controller computed_price
                                            if (isset($item->computed_price) && is_numeric($item->computed_price)) {
                                                $displayPrice = (int) (ceil($item->computed_price / 5) * 5);
                                            } elseif (isset($item->price) && $item->price) {
                                                $displayPrice = $item->price;
                                            }
                                            if ($item->pricesPerPerson && $item->pricesPerPerson->count()) {
                                                $validPrices = $item->pricesPerPerson->where('price_per_person', '>', 0);
                                                // Filter by current start place (like in controller)
                                                if (isset($currentStartPlaceId) && $currentStartPlaceId) {
                                                    $validPrices = $validPrices->filter(function($p) use ($currentStartPlaceId) {
                                                        return (int)$p->start_place_id === (int)$currentStartPlaceId;
                                                    });
                                                }
                                                if ($validPrices->count() > 0) {
                                                    $plnPrices = $validPrices->filter(function($p){
                                                        return isset($p->currency) && (
                                                            (isset($p->currency->symbol) && $p->currency->symbol === 'PLN') ||
                                                            (isset($p->currency->name) && str_contains(strtolower($p->currency->name), 'złot'))
                                                        );
                                                    });
                                                    if ($plnPrices->count() > 0) {
                                                        if (!isset($displayPrice)) {
                                                            $displayPrice = ceil($plnPrices->min('price_per_person') / 5) * 5;
                                                        }
                                                    }
                                                    $grouped = $validPrices->groupBy(function($p){
                                                        return isset($p->currency) && isset($p->currency->symbol) ? $p->currency->symbol : 'OTHER';
                                                    });
                                                    $orderedKeys = [];
                                                    foreach ($grouped->keys() as $k) { if (strtoupper((string)$k) === 'EUR') { $orderedKeys[] = $k; } }
                                                    foreach ($grouped->keys() as $k) { if (strtoupper((string)$k) !== 'EUR') { $orderedKeys[] = $k; } }
                                                    foreach($orderedKeys as $code) {
                                                        $group = $grouped->get($code);
                                                        if ($code === 'PLN') continue;
                                                        $min = $group->min('price_per_person');
                                                        if ($min && $min > 0) {
                                                            $amt = ceil($min);
                                                            $currencySample = $group->first()->currency ?? null;
                                                            $label = $currencySample->symbol ?? $code;
                                                            $otherCurrencyParts[] = $amt . ' ' . $label;
                                                        }
                                                    }
                                                }
                                            }
                                        @endphp
                                        @if($displayPrice)
                                            od <b>{{ $displayPrice }} zł</b>@if(!empty($otherCurrencyParts)) + {{ implode(' + ', $otherCurrencyParts) }}@endif /os.
                                        @else
                                            <b>cena do ustalenia</b>
                                        @endif
                            </div>
                        </div>
                    </div>
                    {{-- clickable previews script removed from loop; single instance added at page end --}}
                @endforeach
                    <a class="check-all" href="{{ $lengthButtonUrl('2') }}">Zobacz wszystkie wycieczki 2-dniowe</a>
                    {{-- przycisk 'Pokaż wszystkie' usunięty na prośbę użytkownika --}}
            </div>
            </div>
        </div>
        <div class="container pt_15 pb_15">
            <div class="package-directory-box">
            <div class="top-text">
                <div class="h2">Wycieczki 3-dniowe</div>
            </div>
            <div class="layout">
                @foreach($random_three_day as $item)
                    <div class="package-preview">
                        <div class="photo" style="background-image:url({{ asset('storage/' . ($item->featured_image ?? '')) }})"></div>
                        <div class="text-window">
                            <div class="title"><a href="{{ $item->prettyUrl() }}">{{ $item->name }}</a></div>
                            <div class="price">
                                @php
                                    $displayPrice = null;
                                    $otherCurrencyParts = [];
                                    // Prefer controller computed_price
                                    if (isset($item->computed_price) && is_numeric($item->computed_price)) {
                                        $displayPrice = (int) (ceil($item->computed_price / 5) * 5);
                                    } elseif (isset($item->price) && $item->price) {
                                        $displayPrice = $item->price;
                                    }
                                    if ($item->pricesPerPerson && $item->pricesPerPerson->count()) {
                                        $validPrices = $item->pricesPerPerson->where('price_per_person', '>', 0);
                                        // Filter by current start place (like in controller)
                                        if (isset($currentStartPlaceId) && $currentStartPlaceId) {
                                            $validPrices = $validPrices->filter(function($p) use ($currentStartPlaceId) {
                                                return (int)$p->start_place_id === (int)$currentStartPlaceId;
                                            });
                                        }
                                        if ($validPrices->count() > 0) {
                                            $plnPrices = $validPrices->filter(function($p){
                                                return isset($p->currency) && (
                                                    (isset($p->currency->symbol) && $p->currency->symbol === 'PLN') ||
                                                    (isset($p->currency->name) && str_contains(strtoupper($p->currency->name), 'ZŁOT'))
                                                );
                                            });
                                            if ($plnPrices->count() > 0) {
                                                if (!isset($displayPrice)) {
                                                    if (isset($item->computed_price) && is_numeric($item->computed_price)) {
                                                        $displayPrice = (int) (ceil($item->computed_price / 5) * 5);
                                                    } else {
                                                        $displayPrice = ceil($plnPrices->min('price_per_person') / 5) * 5;
                                                    }
                                                }
                                            } elseif (!$displayPrice) {
                                                $displayPrice = ceil($validPrices->min('price_per_person') / 5) * 5;
                                            }
                                            $grouped = $validPrices->groupBy(function($p){
                                                return isset($p->currency) && isset($p->currency->symbol) ? $p->currency->symbol : 'OTHER';
                                            });
                                            $orderedKeys = [];
                                            foreach ($grouped->keys() as $k) { if (strtoupper((string)$k) === 'EUR') { $orderedKeys[] = $k; } }
                                            foreach ($grouped->keys() as $k) { if (strtoupper((string)$k) !== 'EUR') { $orderedKeys[] = $k; } }
                                            foreach($orderedKeys as $code) {
                                                $group = $grouped->get($code);
                                                if ($code === 'PLN') continue;
                                                $min = $group->min('price_per_person');
                                                if ($min && $min > 0) {
                                                    $amt = ceil($min);
                                                    $currencySample = $group->first()->currency ?? null;
                                                    $label = $currencySample->symbol ?? $code;
                                                    $otherCurrencyParts[] = $amt . ' ' . $label;
                                                }
                                            }
                                        }
                                    }
                                @endphp
                                @if($displayPrice)
                                    od <b>{{ $displayPrice }} zł</b>@if(!empty($otherCurrencyParts)) + {{ implode(' + ', $otherCurrencyParts) }}@endif /os.
                                @else
                                    <b>cena do ustalenia</b>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
                    <a class="check-all" href="{{ $lengthButtonUrl('3') }}">Zobacz wszystkie wycieczki 3-dniowe</a>
                    {{-- przycisk 'Pokaż wszystkie' usunięty na prośbę użytkownika --}}
            </div>
            </div>
        </div>
        <div class="container pt_15 pb_15">
            <div class="package-directory-box">
            <div class="top-text">
                <div class="h2">Wycieczki 4-dniowe</div>
            </div>
            <div class="layout">
                @foreach($random_four_day as $item)
                    <div class="package-preview">
                        <div class="photo" style="background-image:url({{ asset('storage/' . ($item->featured_image ?? '')) }})"></div>
                        <div class="text-window">
                            <div class="title"><a href="{{ $item->prettyUrl() }}">{{ $item->name }}</a></div>
                            <div class="price">
                                @php
                                    $displayPrice = null;
                                    $otherCurrencyParts = [];
                                    // Prefer controller computed_price
                                    if (isset($item->computed_price) && is_numeric($item->computed_price)) {
                                        $displayPrice = (int) (ceil($item->computed_price / 5) * 5);
                                    } elseif (isset($item->price) && $item->price) {
                                        $displayPrice = $item->price;
                                    }
                                    if ($item->pricesPerPerson && $item->pricesPerPerson->count()) {
                                        $validPrices = $item->pricesPerPerson->where('price_per_person', '>', 0);
                                        // Filter by current start place (like in controller)
                                        if (isset($currentStartPlaceId) && $currentStartPlaceId) {
                                            $validPrices = $validPrices->filter(function($p) use ($currentStartPlaceId) {
                                                return (int)$p->start_place_id === (int)$currentStartPlaceId;
                                            });
                                        }
                                        if ($validPrices->count() > 0) {
                                            $plnPrices = $validPrices->filter(function($p){
                                                return isset($p->currency) && (
                                                    (isset($p->currency->symbol) && $p->currency->symbol === 'PLN') ||
                                                    (isset($p->currency->name) && str_contains(strtoupper($p->currency->name), 'ZŁOT'))
                                                );
                                            });
                                            if ($plnPrices->count() > 0) {
                                                if (!isset($displayPrice)) {
                                                    if (isset($item->computed_price) && is_numeric($item->computed_price)) {
                                                        $displayPrice = (int) (ceil($item->computed_price / 5) * 5);
                                                    } else {
                                                        $displayPrice = ceil($plnPrices->min('price_per_person') / 5) * 5;
                                                    }
                                                }
                                            } elseif (!$displayPrice) {
                                                $displayPrice = ceil($validPrices->min('price_per_person') / 5) * 5;
                                            }
                                            $grouped = $validPrices->groupBy(function($p){
                                                return isset($p->currency) && isset($p->currency->symbol) ? $p->currency->symbol : 'OTHER';
                                            });
                                            $orderedKeys = [];
                                            foreach ($grouped->keys() as $k) { if (strtoupper((string)$k) === 'EUR') { $orderedKeys[] = $k; } }
                                            foreach ($grouped->keys() as $k) { if (strtoupper((string)$k) !== 'EUR') { $orderedKeys[] = $k; } }
                                            foreach($orderedKeys as $code) {
                                                $group = $grouped->get($code);
                                                if ($code === 'PLN') continue;
                                                $min = $group->min('price_per_person');
                                                if ($min && $min > 0) {
                                                    $amt = ceil($min);
                                                    $currencySample = $group->first()->currency ?? null;
                                                    $label = $currencySample->symbol ?? $code;
                                                    $otherCurrencyParts[] = $amt . ' ' . $label;
                                                }
                                            }
                                        }
                                    }
                                @endphp
                                @if($displayPrice)
                                    od <b>{{ $displayPrice }} zł</b>@if(!empty($otherCurrencyParts)) + {{ implode(' + ', $otherCurrencyParts) }}@endif /os.
                                @else
                                    <b>cena do ustalenia</b>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
                    <a class="check-all" href="{{ $lengthButtonUrl('4') }}">Zobacz wszystkie wycieczki 4-dniowe</a>
                    {{-- przycisk 'Pokaż wszystkie' usunięty na prośbę użytkownika --}}
            </div>
            </div>
    </div>
        <div class="container pt_15 pb_15">
            <div class="package-directory-box">
            <div class="top-text">
                <div class="h2">Wycieczki 5-dniowe</div>
            </div>
            <div class="layout">
                @foreach($random_five_day as $item)
                    <div class="package-preview">
                        <div class="photo" style="background-image:url({{ asset('storage/' . ($item->featured_image ?? '')) }})"></div>
                        <div class="text-window">
                            <div class="title"><a href="{{ $item->prettyUrl() }}">{{ $item->name }}</a></div>
                            <div class="price">
                                @php
                                    $displayPrice = null;
                                    $otherCurrencyParts = [];
                                    // Prefer controller computed_price
                                    if (isset($item->computed_price) && is_numeric($item->computed_price)) {
                                        $displayPrice = (int) (ceil($item->computed_price / 5) * 5);
                                    } elseif (isset($item->price) && $item->price) {
                                        $displayPrice = $item->price;
                                    }
                                    if ($item->pricesPerPerson && $item->pricesPerPerson->count()) {
                                        $validPrices = $item->pricesPerPerson->where('price_per_person', '>', 0);
                                        // Filter by current start place (like in controller)
                                        if (isset($currentStartPlaceId) && $currentStartPlaceId) {
                                            $validPrices = $validPrices->filter(function($p) use ($currentStartPlaceId) {
                                                return (int)$p->start_place_id === (int)$currentStartPlaceId;
                                            });
                                        }
                                        if ($validPrices->count() > 0) {
                                            $plnPrices = $validPrices->filter(function($p){
                                                return isset($p->currency) && (
                                                    (isset($p->currency->symbol) && $p->currency->symbol === 'PLN') ||
                                                    (isset($p->currency->name) && str_contains(strtoupper($p->currency->name), 'ZŁOT'))
                                                );
                                            });
                                            if ($plnPrices->count() > 0) {
                                                if (!isset($displayPrice)) {
                                                    $displayPrice = ceil($plnPrices->min('price_per_person') / 5) * 5;
                                                }
                                            } elseif (!$displayPrice) {
                                                $displayPrice = ceil($validPrices->min('price_per_person') / 5) * 5;
                                            }
                                            $grouped = $validPrices->groupBy(function($p){
                                                return isset($p->currency) && isset($p->currency->symbol) ? $p->currency->symbol : 'OTHER';
                                            });
                                            $orderedKeys = [];
                                            foreach ($grouped->keys() as $k) { if (strtoupper((string)$k) === 'EUR') { $orderedKeys[] = $k; } }
                                            foreach ($grouped->keys() as $k) { if (strtoupper((string)$k) !== 'EUR') { $orderedKeys[] = $k; } }
                                            foreach($orderedKeys as $code) {
                                                $group = $grouped->get($code);
                                                if ($code === 'PLN') continue;
                                                $min = $group->min('price_per_person');
                                                if ($min && $min > 0) {
                                                    $amt = ceil($min);
                                                    $currencySample = $group->first()->currency ?? null;
                                                    $label = $currencySample->symbol ?? $code;
                                                    $otherCurrencyParts[] = $amt . ' ' . $label;
                                                }
                                            }
                                        }
                                    }
                                @endphp
                                @if($displayPrice)
                                    od <b>{{ $displayPrice }} zł</b>@if(!empty($otherCurrencyParts)) + {{ implode(' + ', $otherCurrencyParts) }}@endif /os.
                                @else
                                    <b>cena do ustalenia</b>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
                    <a class="check-all" href="{{ $lengthButtonUrl('5') }}">Zobacz wszystkie wycieczki 5-dniowe</a>
                    {{-- przycisk 'Pokaż wszystkie' usunięty na prośbę użytkownika --}}
            </div>
            </div>
        </div>
        <div class="container pt_15 pb_70">
            <div class="top-text">
                <div class="h2">Wycieczki 6-dniowe i dłuższe</div>
            </div>
            <div class="layout">
                @foreach($random_six_day as $item)
                    <div class="package-preview">
                        <div class="photo" style="background-image:url({{ asset('storage/' . ($item->featured_image ?? '')) }})"></div>
                        <div class="text-window">
                            <div class="title"><a href="{{ $item->prettyUrl() }}">{{ $item->name }}</a></div>
                            <div class="price">
                                @php
                                    $displayPrice = null;
                                    $otherCurrencyParts = [];
                                    // Prefer controller computed_price
                                    if (isset($item->computed_price) && is_numeric($item->computed_price)) {
                                        $displayPrice = (int) (ceil($item->computed_price / 5) * 5);
                                    } elseif (isset($item->price) && $item->price) {
                                        $displayPrice = $item->price;
                                    }
                                    if ($item->pricesPerPerson && $item->pricesPerPerson->count()) {
                                        $validPrices = $item->pricesPerPerson->where('price_per_person', '>', 0);
                                        // Filter by current start place (like in controller)
                                        if (isset($currentStartPlaceId) && $currentStartPlaceId) {
                                            $validPrices = $validPrices->filter(function($p) use ($currentStartPlaceId) {
                                                return (int)$p->start_place_id === (int)$currentStartPlaceId;
                                            });
                                        }
                                        if ($validPrices->count() > 0) {
                                            $plnPrices = $validPrices->filter(function($p){
                                                return isset($p->currency) && (
                                                    (isset($p->currency->symbol) && $p->currency->symbol === 'PLN') ||
                                                    (isset($p->currency->name) && str_contains(strtoupper($p->currency->name), 'ZŁOT'))
                                                );
                                            });
                                            if ($plnPrices->count() > 0) {
                                                if (!isset($displayPrice)) {
                                                    $displayPrice = ceil($plnPrices->min('price_per_person') / 5) * 5;
                                                }
                                            } elseif (!$displayPrice) {
                                                $displayPrice = ceil($validPrices->min('price_per_person') / 5) * 5;
                                            }
                                            $grouped = $validPrices->groupBy(function($p){
                                                return isset($p->currency) && isset($p->currency->symbol) ? $p->currency->symbol : 'OTHER';
                                            });
                                            $orderedKeys = [];
                                            foreach ($grouped->keys() as $k) { if (strtoupper((string)$k) === 'EUR') { $orderedKeys[] = $k; } }
                                            foreach ($grouped->keys() as $k) { if (strtoupper((string)$k) !== 'EUR') { $orderedKeys[] = $k; } }
                                            foreach($orderedKeys as $code) {
                                                $group = $grouped->get($code);
                                                if ($code === 'PLN') continue;
                                                $min = $group->min('price_per_person');
                                                if ($min && $min > 0) {
                                                    $amt = ceil($min);
                                                    $currencySample = $group->first()->currency ?? null;
                                                    $label = $currencySample->symbol ?? $code;
                                                    $otherCurrencyParts[] = $amt . ' ' . $label;
                                                }
                                            }
                                        }
                                    }
                                @endphp
                                @if($displayPrice)
                                    od <b>{{ $displayPrice }} zł</b>@if(!empty($otherCurrencyParts)) + {{ implode(' + ', $otherCurrencyParts) }}@endif /os.
                                @else
                                    <b>cena do ustalenia</b>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
                    <a class="check-all" href="{{ $lengthButtonUrl('6plus') }}">Zobacz wszystkie wycieczki 6-dniowe i dłuższe</a>
                    {{-- przycisk 'Pokaż wszystkie' usunięty na prośbę użytkownika --}}
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    (function(){
        function initClickablePreviews() {
            // Make package-preview rows clickable on mobile (<=780px)
            var isMobile = function(){ return window.matchMedia && window.matchMedia('(max-width: 780px)').matches; };
            document.querySelectorAll('.layout').forEach(function(layout){
                layout.querySelectorAll('.package-preview').forEach(function(pp){
                    pp.style.cursor = 'pointer';
                    if (pp.__click_inited) return; pp.__click_inited = true;
                    pp.addEventListener('click', function(e){
                        if (!isMobile()) return;
                        var a = pp.querySelector('.title a');
                        if (a && a.href) {
                            if (e.target.tagName.toLowerCase() === 'a' || e.target.closest('a')) return;
                            window.location = a.href;
                        }
                    });
                });
            });
        }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initClickablePreviews);
    else initClickablePreviews();
    })();
</script>
@endpush
