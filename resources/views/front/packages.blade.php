{{-- @php use App\Models\PackageAmenity;
 use App\Models\Amenity;
 use App\Models\Package; @endphp --}}
@extends('front.layout.master')

@section('head')
    @include('front.partials.seo')
@endsection

@section('main_content')

    <div class="page-top">
        <div class="container">
                    <div class="breadcrumb-container">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/{{ $current_region_slug ?? 'region' }}">Start</a></li>
                            <li class="breadcrumb-item active">Wycieczki szkolne</li>
                        </ol>
                    </div>
                </div>
            </div>

    <div class="package pt_20 pb_50">
        <!-- inline centering overrides removed; styles kept in main CSS -->
        <div class="region-information">
            <div class="text">
                Pokaż ofertę dla:
            <script>
                function autoSubmit() {
                    document.getElementById("regionForm").submit();
                }
            </script>
                {{-- <form name="regionForm" id="regionForm" action="{{ route('packages') }}" method="get"> --}}
                <form name="regionForm" id="regionForm" action="/{{ request()->route('regionSlug') }}/oferty" method="get">
                    <div class="select-form-div">
                        <select name="start_place_id" id="start_place_id_top" class="form-select-region-information">
                            @if(isset($startPlaces))
                                @foreach($startPlaces as $place)
                                    @php $slug = \Illuminate\Support\Str::slug($place->name); @endphp
                                    <option value="{{ $place->id }}" data-slug="{{ $slug }}" @if($start_place_id == $place->id) selected @endif>{{ $place->name }} i okolice</option>
                                @endforeach
                            @endif
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

        <div class="container">
            <div class="row">
                <div class="sidebars col-lg-3 col-md-4 col-xs-1 mb-5" style="padding-right: 30px">
                    <div id="filter-show" class="mobile-filter-button">
                        <div class="show"><i class="fas fa-filter"></i> Pokaż filtry</div>
                    </div>

                    <!-- move this to another file -->
                    <script>
            function toggleFilters() {
                            var div = document.getElementById('filters');
                            var filterShowDiv = document.getElementById('filter-show');

                            // Toggle visibility of filters
                            if (div.style.display !== 'block') {
                                div.style.display = 'block';
                filterShowDiv.innerHTML = '<div class="show"><i class="fas fa-filter"></i> Ukryj filtry</div>';
                            } else {
                                div.style.display = 'none';
                filterShowDiv.innerHTML = '<div class="show"><i class="fas fa-filter"></i> Pokaż filtry</div>';
                            }
                        }

                        function updateFiltersDisplay() {
                            var div = document.getElementById('filters');
                            const viewportWidth = window.innerWidth;
                            var filterShowDiv = document.getElementById('filter-show');

                            if (viewportWidth < 798) {
                                // Ensure that the toggle button works only when the screen is small
                                if (!filterShowDiv.onclick) {
                                    filterShowDiv.onclick = toggleFilters; // Assign onclick if not already assigned
                                }

                                // Hide the filters when screen is small
                                if (div.style.display !== 'block') {
                                    div.style.display = 'none';
                                }
                            } else {
                                // Show the filters when the screen is large enough
                                div.style.display = 'block';

                                // Remove onclick listener when the screen is large
                                if (filterShowDiv.onclick) {
                                    filterShowDiv.onclick = null;
                                }
                            }
                        }

                        // Call once on page load to initialize everything
                        window.onload = function() {
                            updateFiltersDisplay();
                        }

                        // Attach resize event to handle window resizing
                        window.addEventListener('resize', function() {
                            updateFiltersDisplay();  // Call update on resize
                            // Re-attach the toggleFilters in case it's missing after resizing
                            if (window.innerWidth < 798 && !document.getElementById('filter-show').onclick) {
                                document.getElementById('filter-show').onclick = toggleFilters;
                            }
                        });

                    </script>

                   

                    <div id="filters" class="package-sidebar filters-compact">
                        {{-- <form action="{{ route('packages') }}" method="get"> --}}
                        <form id="sidebarFilterForm" action="/{{ request()->route('regionSlug') }}/oferty" method="get">
                            <div class="widget">
                                <h2>Pokaż ofertę dla</h2>
                                <div class="box">
                                    <select name="start_place_id" id="start_place_id_sidebar" class="form-select">
                                        @if(isset($startPlaces))
                                            @foreach($startPlaces as $place)
                                                @php $slug = \Illuminate\Support\Str::slug($place->name); @endphp
                                                <option value="{{ $place->id }}" data-slug="{{ $slug }}" @if($start_place_id == $place->id) selected @endif>{{ $place->name }} i okolice</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                            </div>
<script>
// Helper: set cookie
function setCookie(name, value, days) {
    var expires = "";
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days*24*60*60*1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (value || "")  + expires + "; path=/";
}

// Helper: get cookie
function getCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0;i < ca.length;i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1,c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
}

// Save start_place_id to cookie
function saveStartPlaceId(val) {
    setCookie('start_place_id', val, 30);
}

function syncStartPlaceSelects(newVal) {
    const top = document.getElementById('start_place_id_top');
    const side = document.getElementById('start_place_id_sidebar');
    if (top && top.value !== newVal) top.value = newVal;
    if (side && side.value !== newVal) side.value = newVal;
}

// On page load: save current start_place_id to cookie only if backend used default Warszawa
document.addEventListener('DOMContentLoaded', function() {
    const serverVal = '{{ $start_place_id }}';
    const cookieVal = getCookie('start_place_id');
    // Jeżeli cookie różni się od wartości serwera (slug wybrany), nadpisujemy cookie i selecty wartością serwera
    if (cookieVal !== serverVal) {
        setCookie('start_place_id', serverVal, 30);
        syncStartPlaceSelects(serverVal);
    } else {
        syncStartPlaceSelects(serverVal);
    }
    @if($usedDefaultWarszawa)
        setCookie('start_place_id', serverVal, 30);
    @endif

    const top = document.getElementById('start_place_id_top');
    const side = document.getElementById('start_place_id_sidebar');
    function onChange(e){
        const select = e.target;
        const val = select.value;
        const slug = select.options[select.selectedIndex].getAttribute('data-slug') || 'region';
        // Aktualizacja od razu
        syncStartPlaceSelects(val);
        setCookie('start_place_id', val, 30);
        // Zbuduj query i upewnij się, że zawsze wysyłamy start_place_id -> backend może wtedy wymusić availability filter
        const form = document.getElementById('sidebarFilterForm');
        const params = new URLSearchParams(new FormData(form));
        // Zamiast usuwać start_place_id, ustawiamy jawnie na aktualną wartość selekta
        params.set('start_place_id', val);
        const qs = params.toString();
        const target = '/' + slug + '/oferty' + (qs ? ('?' + qs) : '');
        window.location.replace(target);
    }
    if (top) top.addEventListener('change', onChange);
    if (side) side.addEventListener('change', onChange);
});
</script>
                            <div class="widget">
                                <h2>Sortuj według</h2>
                                <div class="box">
                                    <select name="sort_by" class="form-select">
                                        <option value="">Domyślne sortowanie</option>
                                        <option value="price_asc" @if(request('sort_by') == 'price_asc') selected @endif>Cena: od najniższej</option>
                                        <option value="price_desc" @if(request('sort_by') == 'price_desc') selected @endif>Cena: od najwyższej</option>
                                        <option value="name_asc" @if(request('sort_by', 'name_asc') == 'name_asc') selected @endif>Alfabetycznie A-Z</option>
                                        <option value="name_desc" @if(request('sort_by') == 'name_desc') selected @endif>Alfabetycznie Z-A</option>
                                        <option value="duration_asc" @if(request('sort_by') == 'duration_asc') selected @endif>Czas trwania: rosnąco</option>
                                        <option value="duration_desc" @if(request('sort_by') == 'duration_desc') selected @endif>Czas trwania: malejąco</option>
                                    </select>
                                </div>
                            </div>
                            <div class="widget">
                                <h2>Długość wycieczki</h2>
                                <div class="box">
                                    <select name="length_id" class="form-select">
                                        <option value="">Wszystkie długości</option>
                                        <option value="1" @if(request('length_id') == '1') selected @endif>1 dzień</option>
                                        <option value="2" @if(request('length_id') == '2') selected @endif>2 dni</option>
                                        <option value="3" @if(request('length_id') == '3') selected @endif>3 dni</option>
                                        <option value="4" @if(request('length_id') == '4') selected @endif>4 dni</option>
                                        <option value="5" @if(request('length_id') == '5') selected @endif>5 dni</option>
                                        <option value="6plus" @if(request('length_id') == '6plus') selected @endif>6 dni i więcej</option>
                                    </select>
                                </div>
                            </div>
                        {{-- Usunięto widget filtrowania po cenie (min/max) na prośbę użytkownika --}}
<div class="widget">
    <h2>Kierunek</h2>
    <div class="box">
        <input type="text" name="destination_name" id="destination_name" class="form-control" placeholder="Wpisz kierunek" value="{{ request('destination_name', '') }}">
    </div>
</div>
                            <div class="widget">
                                <h2>Typ wycieczki</h2>
                                <div class="box">
                                    <select name="event_type_id" class="form-select">
                                        <option value="">Wszystkie typy wycieczek</option>
                                        @if(isset($eventTypes))
                                            @foreach($eventTypes as $eventType)
                                                <option value="{{ $eventType->id }}" @if(request('event_type_id') == $eventType->id) selected @endif>{{ $eventType->name }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                            </div>
                            @if(auth()->check())
                            <div class="widget">
                                <h2>Tagi (dla zalogowanych)</h2>
                                <div class="box">
                                    <input type="text" name="tags" id="tags" class="form-control" placeholder="Wpisz tagi (po przecinku)" value="{{ request('tags') }}" list="tags_list">
                                    <datalist id="tags_list">
                                        @if(isset($allTags))
                                            @foreach($allTags as $t)
                                                <option value="{{ $t->name }}">{{ $t->name }}</option>
                                            @endforeach
                                        @endif
                                    </datalist>
                                    <small style="color:#666;">Możesz wpisać jeden lub wiele tagów, oddzielając je przecinkami. Autouzupełnianie podpowiada dostępne tagi.</small>
                                </div>
                            </div>
                            @endif
                            @php
                                $selectedTransportTypeIds = array_map(
                                    'strval',
                                    array_filter((array) request('transport_type_id', []))
                                );
                            @endphp
                            <div class="widget">
                                <h2>Środek transportu</h2>
                                <div class="box">
                                    @if(isset($transportTypes))
                                        @foreach($transportTypes as $transportType)
                                            <label class="d-block mb-1">
                                                <input
                                                    type="checkbox"
                                                    name="transport_type_id[]"
                                                    value="{{ $transportType->id }}"
                                                    @checked(in_array((string) $transportType->id, $selectedTransportTypeIds, true))
                                                >
                                                {{ $transportType->name }}
                                            </label>
                                        @endforeach
                                    @endif
                                    <small class="text-muted d-block mt-2">
                                        Zaznacz jeden lub więcej — np. sam autokar albo autokar i pociąg razem.
                                    </small>
                                </div>
                            </div>
                        <div class="filter-button">
                            <button type="submit" class="btn btn-primary">Filtruj</button>
                        </div>
                    </form>
                    </div>
                </div>


                 <div class="col-lg-9 col-md-8 col-xs-1">
                    <div id="packages-results">
                        @include('front.partials.packages-list', [
                            'eventTemplate' => $eventTemplate,
                            'requestedQty' => request('qty') ? (int) request('qty') : null,
                            'start_place_id' => $start_place_id ?? null,
                        ])
                    </div>
                    <div id="infinite-loader" style="display:none; text-align:center; padding:20px; color:#666;">Ładuję kolejne oferty…</div>
                    <div id="infinite-end" style="display:none; text-align:center; padding:20px; color:#666;">To już wszystkie wyniki.</div>
                    <div id="infinite-scroll-sentinel" style="height:1px;"></div>
                    <div id="packages-loading" style="display:none; padding: 8px 0; color:#666;">
                        Szukam wycieczek…
                    </div>
                </div>


    </div>
@endsection

@push('scripts')
<script>
(function(){
    // Scrollowalny sidebar
    var filters = document.getElementById('filters');
    if(filters){
        filters.classList.add('filters-scroll-enhanced');
    }
    var style = document.createElement('style');
    style.innerHTML = `
    .filters-scroll-enhanced{position:sticky;top:15px;max-height:calc(100vh - 30px);overflow-y:auto;}
    .filters-scroll-enhanced::-webkit-scrollbar{width:8px;}
    .filters-scroll-enhanced::-webkit-scrollbar-track{background:#f1f1f1;}
    .filters-scroll-enhanced::-webkit-scrollbar-thumb{background:#c1c1c1;border-radius:4px;}
    .filters-scroll-enhanced::-webkit-scrollbar-thumb:hover{background:#a1a1a1;}
    `;
    document.head.appendChild(style);

    // Infinite scroll
    var sentinel = document.getElementById('infinite-scroll-sentinel');
    var results = document.getElementById('packages-results');
    var loader = document.getElementById('infinite-loader');
    var endMarker = document.getElementById('infinite-end');
    if(!sentinel || !results) return; // nic do roboty

    // Sekwencyjne ładowanie stron: nie inkrementujemy lokalnie, tylko używamy next_page z backendu
    var initialPage = parseInt(new URLSearchParams(window.location.search).get('page') || '1',10);
    var nextPage = initialPage + 1; // pierwsze dociągnięcie to kolejna strona po tej, która jest wyrenderowana serwerowo
    var isLoading = false;
    var hasMore = true; // backend zweryfikuje i zwróci has_more/next_page

    function buildBaseUrl(){
        var url = new URL(window.location.href);
        url.searchParams.delete('page');
        return url;
    }

    function loadNext(){
        if(isLoading || !hasMore) return;
        isLoading = true;
        loader.style.display='block';
        var url = buildBaseUrl();
        // Ładujemy dokładnie stronę wskazaną przez backend (next_page), a nie current+1
        url.searchParams.set('page', nextPage);
        url.searchParams.set('partial', '1');
        fetch(url.toString(), { credentials: 'same-origin', headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'} })
            .then(r=>{ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
            .then(data=>{
                if(data && data.html){
                    var tmp = document.createElement('div');
                    tmp.innerHTML = data.html;
                    // przenieś tylko .item
                    tmp.querySelectorAll('.item').forEach(function(el){ results.appendChild(el); });
                }
                // Zaufaj backendowi: next_page wyznacza następną stronę do pobrania; null => koniec
                if (data && typeof data.next_page !== 'undefined' && data.next_page !== null) {
                    // Jeśli backend zwraca też current_page, to kolejną stroną jest current_page+1.
                    // Jednak preferujemy jawne next_page z backendu.
                    nextPage = data.next_page;
                    hasMore = !!data.has_more;
                } else {
                    hasMore = false;
                    endMarker.style.display='block';
                }
            })
            .catch(()=>{ /* fallback: zatrzymaj infinite scroll */ hasMore=false; })
            .finally(()=>{ loader.style.display='none'; isLoading=false; observer.observe(sentinel); });
    }

    var observer = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
            if(entry.isIntersecting){
                // Od razu przestajemy obserwować, aby nie wyzwalać wielu requestów
                observer.unobserve(sentinel);
                // Jeśli trwa ładowanie, nie rób nic — wznawianie obserwacji następuje w finally()
                if (!isLoading) {
                    loadNext();
                }
            }
        });
    }, {rootMargin:'400px 0px'});

    observer.observe(sentinel);

})();
</script>
@endpush
