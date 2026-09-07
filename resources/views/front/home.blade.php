                                                    
@extends('front.layout.master')

@section('head')
    @include('front.partials.seo')
    @if(isset($homeFaqs) && $homeFaqs->isNotEmpty())
        <x-seo.json-ld :schemas="[\App\Support\Seo\SchemaBuilder::faqPage($homeFaqs)]" />
    @endif
@endsection

@section('main_content')
    <div class="container package-page-layout package-page-border pt_80">
        <div class="destination">
            <h1 class="destination_question">Wyszukaj swoją wycieczkę szkolną!</h1>
            {{-- TODO: Convert back to dynamic form with action="{{ route('packages') }}" method="get" --}}
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
        </div>

        <script>
            const destinationFrom = document.querySelector('.destination_from');
            const destinationFromSelectOption = document.querySelector('.destination_from_select_option');
            const soValue = document.querySelector('#soValue');
            const optionSearch = document.querySelector('#optionSearch');
            const destinationFromOptions = document.querySelector('.destination_from_options');
            const destinationFromOptionsList = document.querySelectorAll('.destination_from_options li');

            if (destinationFromSelectOption) {
                destinationFromSelectOption.addEventListener('click',function(){
                    destinationFrom.classList.toggle('active');
                });
            }

            if (destinationFromOptionsList.length > 0) {
                destinationFromOptionsList.forEach(function(destinationFromOptionsListSingle){
                    destinationFromOptionsListSingle.addEventListener('click',function(){
                        text = this.textContent;
                        if (soValue) soValue.value = text;
                        if (destinationFrom) destinationFrom.classList.remove('active');
                    })
                });
            }

            if (optionSearch && destinationFromOptions) {
                optionSearch.addEventListener('keyup',function(){
                    var filter, li, i, textValue;
                    filter = optionSearch.value.toUpperCase();
                    li = destinationFromOptions.getElementsByTagName('li');
                    for(i = 0; i < li.length; i++){
                        liCount = li[i];
                        textValue = liCount.textContent || liCount.innerText;
                        if(textValue.toUpperCase().indexOf(filter) > -1){
                            li[i].style.display = '';
                        }else{
                            li[i].style.display = 'none';
                        }
                    }
                });
            }


        </script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var select = document.getElementById('start_place_id_select');
            var whereFromOption = document.getElementById('where_from_option');
            var cookieVal = getCookie('start_place_id');
            if (cookieVal && select) {
                select.value = cookieVal;
                if (whereFromOption) whereFromOption.style.display = 'none';
            } else {
                if (whereFromOption) whereFromOption.style.display = '';
                if (select) select.value = '';
            }
        });
        </script>



        <script>
            let select = document.getElementById("select")
            let list = document.getElementById("list")
            let selectText = document.getElementById("selectText")
            let destination_options = document.getElementsByClassName("destination_options")

            if (select && list) {
                select.onclick = function(){
                    list.classList.toggle("open");
                };
            }

            if (destination_options.length > 0 && selectText) {
                for(destination_option of destination_options){
                    destination_option.onclick = function (){
                        selectText.innerHTML = this.innerHTML;
                    }
                }
            }

        </script>

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
                console.log('Saved start_place_id to cookie:', val);
            }
        </script>

                    </div>

    <div class="container pt_70">
        <div class="carousel-header"><h2>Polecane wycieczki szkolne</h2></div>
    <div class="carousel">
        <div id="carouselExampleControls" class="carousel carousel-dark slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                @foreach($random_chunks as $index => $chunk)
                    <div class="carousel-item @if($index == 0) active @endif">
                        <div class="card-wrapper">
                            @foreach($chunk as $item)
                                <div class="card">
                                    <div class="image-wrapper" style="width: 100%; aspect-ratio: 4 / 3; overflow: hidden;">
                                        <img src="{{ $item->preview_image_url ?: asset('uploads/default.png') }}" class="card-img-top" alt="{{ $item->name }}" style="width: 100%; height: 100%; object-fit: cover; object-position: center; display: block;" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='{{ $item->full_image_url ?: asset('uploads/default.png') }}';">
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title">{{ $item->name }}</h5>
                                        <div class="card-text">
                                            <div class="price"><i class="far fa-clock"></i>&nbsp&nbsp{{ $item->length->name }}</div>
                                            @php
                                                $transportTypes = $item->relationLoaded('transportTypes') ? $item->transportTypes : collect();
                                            @endphp
                                            @if($transportTypes->count())
                                                <div class="price" style="display:flex; align-items:center; gap:0;">
                                                    @foreach($transportTypes as $index => $transportType)
                                                        @php
                                                            $name = trim(strtolower($transportType->name ?? ''));
                                                            $iconHtml = '<i class="fa-solid fa-train" style="margin-right:4px;"></i>';
                                                            if (str_contains($name, 'autokar') || str_contains($name, 'autobus')) {
                                                                $iconHtml = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="1.2em" height="1.2em" style="margin-right:4px; vertical-align: -0.15em; fill: currentColor; display: inline-block;"><path d="M480 64C568.4 64 640 135.6 640 224L640 448C640 483.3 611.3 512 576 512L570.4 512C557.2 549.3 521.8 576 480 576C438.2 576 402.7 549.3 389.6 512L250.5 512C237.3 549.3 201.8 576 160.1 576C118.4 576 82.9 549.3 69.7 512L64 512C28.7 512 0 483.3 0 448L0 160C0 107 43 64 96 64L480 64zM160 432C133.5 432 112 453.5 112 480C112 506.5 133.5 528 160 528C186.5 528 208 506.5 208 480C208 453.5 186.5 432 160 432zM480 432C453.5 432 432 453.5 432 480C432 506.5 453.5 528 480 528C506.5 528 528 506.5 528 480C528 453.5 506.5 432 480 432zM480 128C462.3 128 448 142.3 448 160L448 352C448 369.7 462.3 384 480 384L544 384C561.7 384 576 369.7 576 352L576 224C576 171 533 128 480 128zM248 288L352 288C369.7 288 384 273.7 384 256L384 160C384 142.3 369.7 128 352 128L248 128L248 288zM96 128C78.3 128 64 142.3 64 160L64 256C64 273.7 78.3 288 96 288L200 288L200 128L96 128z"/></svg>';
                                                            } elseif (str_contains($name, 'pociąg') || str_contains($name, 'pociag')) {
                                                                $iconHtml = '<i class="fa-solid fa-train" style="margin-right:4px;"></i>';
                                                            } elseif (str_contains($name, 'samolot')) {
                                                                $iconHtml = '<i class="fa-solid fa-plane" style="margin-right:4px;"></i>';
                                                            } elseif (str_contains($name, 'prom')) {
                                                                $iconHtml = '<i class="fa-solid fa-sailboat" style="margin-right:4px;"></i>';
                                                            } elseif (str_contains($name, 'minibus')) {
                                                                $iconHtml = '<i class="fa-solid fa-van-shuttle" style="margin-right:4px;"></i>';
                                                            }
                                                        @endphp
                                                        <span style="margin:0 3px 0 0; white-space:nowrap;">
                                                            {!! $iconHtml !!}{{ $transportType->name }}
                                                        </span>
                                                        @if($index < $transportTypes->count() - 1)
                                                            <span style="margin:0 3px;">+</span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                            <div class="price" id="price-accent">
                                                @php
                                                    $filterStartPlaceId = null;
                                                    if (isset($start_place_id) && $start_place_id) {
                                                        $filterStartPlaceId = (int) $start_place_id;
                                                    } else {
                                                        $filterStartPlaceId = \App\Models\Place::where('slug', 'warszawa')->value('id')
                                                            ?? \App\Models\Place::where('name', 'Warszawa')->value('id');
                                                        if ($filterStartPlaceId) {
                                                            $filterStartPlaceId = (int) $filterStartPlaceId;
                                                        }
                                                    }

                                                    $priceData = \App\Support\PriceDisplay::collectVariants($item, $filterStartPlaceId);
                                                    $primaryVariant = $priceData['primary'];
                                                    $displayParts = $primaryVariant ? explode(' + ', $primaryVariant['display']) : [];
                                                @endphp
                                                <div class="price-multiline">
                                                    @if($primaryVariant)
                                                        <div>od&nbsp;<b>{{ $displayParts[0] ?? $primaryVariant['display'] }}</b></div>
                                                        @foreach(array_slice($displayParts, 1) as $part)
                                                            <div>+ {{ $part }}</div>
                                                        @endforeach
                                                    @else
                                                        <div>od&nbsp;<b>—</b></div>
                                                    @endif
                                                    <div class="price-note">za osobę</div>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="{{ $item->prettyUrl() }}" class="offer">Pokaż ofertę</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleControls" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleControls" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div></div>

    <div class="container pt_70">
        <div class="redirect-set">
        <div class="redirect-documents-buttons">
            <div class="redirect-header"><h2>Dokumenty<br></h2></div>
            <div class="documents-button">
                <div class="together">
                    &nbsp;Przejdź do najważniejszych dokumentów&nbsp;<a href="{{ route('documents.global') }}" class="redirect-arrow"><i class="fas fa-arrow-right"></i></a></div>
            </div>
            <div class="redirect-header"><h2>Ubezpieczenia<br></h2></div>
            <div class="insurance-button">
                <div class="together">
                    &nbsp;Przejdź do szczegółów ubezpieczeń&nbsp;<a href="{{ route('insurance') }}" class="redirect-arrow"><i class="fas fa-arrow-right"></i></a></div>
            </div>

        </div>
        <div class="redirect-decoration-photo"><img src="{{ asset('uploads/szlakiem_zamkow_krzyzackich.webp') }}" alt="Szlakiem Zamków Krzyżackich"></div>
        </div>
    </div>

    <style>
        .home-banner-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100%;
            min-height: 100%;
            background: #e6f0fa;
            z-index: 1;
        }
        .banner-details .details h2,
        .banner-details .details p {
            color: #222831 !important;
            text-shadow: none !important;
        }
    .blog.pt_70 .item.pb_70 {
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .blog.pt_70 .photo {
        flex-shrink: 0;
    }
    .blog.pt_70 .text {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
    }
    .blog.pt_70 .short-des {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        align-items: flex-start;
    }
    .blog.pt_70 .button-style-2.mt_5 {
        margin-top: auto;
    }

    /* Responsywność: na mobile .item height:auto */
    @media (max-width: 767.98px) {
        .blog.pt_70 .item.pb_70 {
            height: auto;
        }
    }

    .banner {
        position: relative;
        width: 100vw;
        min-width: 100vw;
        overflow: hidden;
        margin-top: 60px;
        padding: 50px 0;
        background: #e6f0fa;
    }

    .banner-inner {
        position: relative;
        z-index: 2;
        margin-top: 0;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
        width: 100%;
        background: transparent;
        box-shadow: none;
    }

    .banner-content {
        display: flex;
        align-items: center;
        gap: 2rem;
    }

    .banner-image {
        flex: 0 0 46%;
        max-width: 46%;
    }

    .banner-image img {
        width: 100%;
        height: 100%;
        min-height: 320px;
        object-fit: cover;
        border-radius: 18px;
        box-shadow: 0 20px 35px rgba(0, 0, 0, 0.25);
    }

    .banner-details {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 1.75rem;
        text-align: left;
    }

    .banner-details .details h2 {
        font-size: clamp(2rem, 2.8vw, 2.75rem);
        font-weight: 700;
        line-height: 1.2;
        margin-bottom: 0;
        color: #ffffff;
    }

    .banner-details .details p {
        font-size: 1.05rem;
        line-height: 1.75;
        color: rgba(255, 255, 255, 0.9);
        margin: 0;
    }

    .banner-details .buttons .link_button {
        padding: 0.85rem 2.5rem;
        font-size: 1.05rem;
        border-radius: 999px;
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);
        color: #ffffff;
    }

    .banner-details .details {
        width: 100%;
    }

    .banner-details .buttons {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    @media (max-width: 1199.98px) {
        .banner-content {
            gap: 1.75rem;
        }
    }

    @media (max-width: 991.98px) {
        .banner {
            margin-top: 40px;
            padding: 40px 0;
        }

        .banner-inner {
            margin-top: 0;
        }

        .banner-content {
            flex-direction: column;
            text-align: center;
        }

        .banner-image,
        .banner-details {
            max-width: 100%;
        }

        .banner-details {
            align-items: center;
            gap: 1.5rem;
        }

        .banner-image img {
            min-height: 0;
            margin: 0 auto 1.5rem;
            box-shadow: 0 16px 28px rgba(0, 0, 0, 0.22);
        }

        .banner-details .buttons {
            justify-content: center;
        }
    }

    @media (max-width: 575.98px) {
        .banner {
            margin-top: 32px;
            padding: 32px 0;
        }

        .banner-inner {
            margin-top: 0;
        }

        .banner-content {
            gap: 1.5rem;
        }

        .banner-details .details h2 {
            font-size: 1.85rem;
        }

        .banner-details .details p {
            font-size: 1rem;
        }
    }
    </style>


<div class="banner">
    <div class="home-banner-bg"></div>
    <div class="banner-inner">
        <div class="banner-content">
            <div class="banner-image">
                <img src="{{ asset('storage/turysci.jpg') }}" alt="Uczniowie podczas wycieczki">
            </div>
            <div class="banner-details">
                <div class="details">
                    <h2>Wycieczki szkolne, które tworzą wspomnienia na całe życie!</h2>
                    <p>Biuro Podroży RAFA specjalizuje się w organizacji wycieczek szkolnych, które łączą przygodę, naukę i rozwój. Niezależnie od tego, czy wybierasz wyjazd krajowy, czy zagraniczny - zapewniamy profesjonalną obsługę, bezpieczeństwo oraz niezapomniane wrażenia. Z nami każda podróż to krok ku nowym doświadczeniom!</p>
                </div>
                <div class="buttons">
                    <a href="{{ route('packages') }}" class="link_button">Sprawdź ofertę</a>
                </div>
            </div>
        </div>
    </div>
</div>
    </div>

    <div class="blog pt_70">
        <div class="container ">
            <div class="row">
                <div class="col-md-12">
                    <div class="heading">
                        <h2>Aktualności</h2>
                        <p>
                            Ostatnie wpisy i aktualności z naszego bloga
                        </p>
                    </div>
                </div>
            </div>
            <div class="row">
                @if($blogPosts->count() > 0)
                    @foreach($blogPosts as $index => $post)
                        <div class="col-lg-4 col-md-6 {{ $index >= 2 ? 'd-none d-lg-block' : '' }}">
                            <div class="item pb_70">
                                <div class="photo">
                                    @if($post->featured_image)
                                        <img src="{{ asset('storage/' . $post->featured_image) }}" alt="{{ $post->title }}" loading="lazy" decoding="async" />
                                    @else
                                        <img src="{{ asset('uploads/blog-placeholder.jpg') }}" alt="{{ $post->title }}" />
                                    @endif
                                </div>
                                <div class="text">
                                    <h2>
                                        <a href="{{ route('blog.post.global', $post->slug) }}">{{ $post->title }}</a>
                                    </h2>
                                    <div class="short-des">
                                        <p>
                                            @if($post->excerpt)
                                                {{ $post->excerpt }}
                                            @else
                                                {{ \Illuminate\Support\Str::limit(strip_tags($post->content), 150) }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="button-style-2 mt_5">
                                        <a href="{{ route('blog.post.global', $post->slug) }}">Czytaj dalej</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="col-lg-12">
                        <p class="text-center">Brak aktualności do wyświetlenia.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="container pt_70">
    <div class="description">
        <div class="first-illustration">
            <div class="first-box">
                <div class="title"><h2 style="font-weight: 700">Biuro Podróży RAFA – wycieczki szkolne w całej Polsce</h2></div>
                <div class="intro">
                    <div class="intro-1">O nas</div><div class="intro-2">&nbsp;· od 2011 roku</div>
                </div>
                <div class="intro-a">
                    <p style="font-size: larger">
                        <b>Biuro Podróży RAFA to wyspecjalizowany organizator wycieczek szkolnych i wyjazdów grupowych, działający nieprzerwanie od 2011 roku.</b>
                        Organizujemy wyjazdy w Polsce i Europie, kompleksowo zajmując się ich przygotowaniem i realizacją.
                    </p>
                    <p>
                        W 2025 roku w organizowanych przez nas wyjazdach uczestniczyło ponad 20&nbsp;000 osób.
                        Na naszej stronie dostępnych jest ponad 11&nbsp;000 aktualnych wariantów wycieczek szkolnych
                        z 55 miejscowości wyjazdu w całej Polsce.
                    </p>
                    <p>
                        <a href="{{ route('about.global') }}">Poznaj nas bliżej →</a>
                        · <a href="{{ route('guide.global') }}">Poradnik przed wyjazdem →</a>
                    </p>
                </div>
            </div>
            <div class="illustration">
                <img src="{{ asset('uploads/description-illustration.svg')}}" alt="">
            </div>
        </div>

        <div class="second-box">
            <div class="offer">
                <div style="font-size: larger"><b>Technologia wspierająca organizację wyjazdów</b></div>
                <p>
                    Dzięki zaawansowanym rozwiązaniom technologicznym oferty są automatycznie kalkulowane
                    i aktualizowane w czasie rzeczywistym, dzięki czemu klienci mogą samodzielnie znaleźć
                    aktualny wariant wycieczki dopasowany do miejsca wyjazdu i swoich potrzeb.
                </p>
                <p>
                    <b>Doświadczenie, skala i technologia</b> — aby organizacja wycieczki szkolnej była
                    prosta, bezpieczna i przewidywalna.
                </p>
                <p>
                    Więcej o naszym doświadczeniu, standardach bezpieczeństwa i modelu współpracy:
                    <a href="{{ route('about.global') }}"><b>O nas</b></a>.
                </p>
                <div class="link"><a href="{{ route('about.global') }}"> <i class="fas fa-arrow-circle-right"></i></a></div>
            </div>
            <div class="why">
                <div style="font-size: larger">
                    <b>RAFA w liczbach</b>
                </div>
                <ul>
                    <li><b>Od 2011</b> — nieprzerwana działalność jako organizator wycieczek szkolnych i wyjazdów grupowych.</li>
                    <li><b>20&nbsp;000+</b> uczestników wyjazdów w 2025 roku.</li>
                    <li><b>11&nbsp;000+</b> aktualnych wariantów wycieczek szkolnych na stronie.</li>
                    <li><b>55 miejscowości</b> wyjazdu w całej Polsce.</li>
                </ul>
            </div>
        </div>

        <div class="third-box">
            <div class="outro"><p><div style="font-size: larger"><b>Skontaktuj się z nami!</b></div><p><br>
                    Zapraszamy do kontaktu z Biurem Podróży RAFA. Nasz zespół z chęcią pomoże w zaplanowaniu Twojej wymarzonej wycieczki szkolnej! Zadzwoń lub wyślij zapytanie, a my przygotujemy ofertę dopasowaną do Twoich potrzeb.<br><br><b>Zarezerwuj wycieczkę już dziś i twórz wspomnienia na całe życie!</b></p>
                <div class="link"><a href="{{ route('contact') }}"> <i class="fas fa-arrow-circle-right"></i></a></div></div>
        </div>

        @if(isset($homeFaqs) && $homeFaqs->isNotEmpty())
            <div class="container pt_50 pb_50">
                <x-seo.faq-section :faqs="$homeFaqs" title="Najczęstsze pytania o wycieczki szkolne" idPrefix="home-faq" />
                <p class="text-center mt-3"><a href="{{ route('faq') }}">Zobacz wszystkie pytania FAQ →</a></p>
            </div>
        @endif
    </div>
    </div>
@endsection
