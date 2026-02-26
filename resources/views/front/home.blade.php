                                                    
@extends('front.layout.master')

@section('head')
    @include('front.partials.seo')
@endsection

@section('main_content')
    <div class="container package-page-layout package-page-border pt_80">
        <div class="destination">
            <h2 class="destination_question">Wyszukaj swoją wycieczkę szkolną!</h2>
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
                                    <div class="image-wrapper">
                                        <img src="{{ asset('storage/' . ($item->featured_image ?? '')) }}" class="card-img-top" alt="...">
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title">{{ $item->name }}</h5>
                                        <div class="card-text">
                                            <div class="price"><i class="far fa-clock"></i>&nbsp&nbsp{{ $item->length->name }}</div>
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
        overflow: hidden;
        margin-top: 60px;
        padding: 50px 0;
    }

    .banner-inner {
        position: relative;
        z-index: 2;
        margin-top: 0;
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
    <div class="home-banner-overlay"></div>
    <div class="container banner-inner">
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
                                        <img src="{{ asset('storage/' . $post->featured_image) }}" alt="{{ $post->title }}" />
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
                                                {{ Str::limit(strip_tags($post->content), 150) }}
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
                <div class="title"><h2 style="font-weight: 700">Biuro Podróży RAFA</h2></div>
                <div class="intro">
                    <div class="intro-1"> Wycieczki Szkolne i Wyjazdy Grupowe</div><div class="intro-2">&nbsp;z całej Polski</div>
                </div>
                <div class="intro-a"><p><div style="font-size: larger"><b>Witamy na stronie Biura Podróży RAFA – profesjonalnego organizatora wycieczek szkolnych, wyjazdów integracyjnych i wycieczek edukacyjnych w Polsce i za granicą.</b></div><p><br> Jako doświadczony lider w branży turystycznej, oferujemy kompleksową obsługę wycieczek ze wszystkich województw.<br>Z nami każda podróż staje się niezapomnianą przygodą!</p></div>
            </div>
            <div class="illustration" >
                <img src="{{ asset('uploads/description-illustration.svg')}}" alt=""> </div>
            </div>


        <div class="second-box">
            <div class="offer">
                <div style="font-size: larger"><b>Nasza oferta obejmuje szeroką gamę wyjazdów szkolnych, które łączą edukację z rozrywką.</b></div>
                <p>Proponujemy wycieczki krajowe 1-dniowe, 2-dniowe, 3-dniowe, 4-dniowe oraz 5-dniowe, a także zagraniczne wycieczki szkolne do popularnych europejskich destynacji, takich jak Praga, Rzym, Berlin, Wiedeń czy Paryż. Organizujemy wyjazdy dla grup z całej Polski.</p>
                <h5 class="mb-2"><b>Nasza oferta</b></h5>
                <ul>
                    <li><b>Wycieczki szkolne w Polsce</b> – odkryj piękno kraju z Biurem Podróży RAFA! Organizujemy wyjazdy edukacyjne i krajoznawcze do wszystkich regionów Polski – od górskich szlaków w Bieszczadach, Tatrach i Sudetach, przez malownicze parki narodowe, po nadmorskie kurorty Bałtyku. W naszej ofercie znajdują się zarówno wycieczki do najpiękniejszych polskich miast – Krakowa, Warszawy, Wrocławia, Gdańska czy Poznania – jak i wyprawy do miejsc pełnych natury, legend i historii. Z nami każda szkoła może odkrywać Polskę na nowo, w sposób ciekawy, bezpieczny i dopasowany do wieku oraz potrzeb uczniów.</li>
                    <li><b>Wycieczki zagraniczne</b> – odkrywaj Europę z Biurem Podróży RAFA! Organizujemy szkolne wyjazdy do najpiękniejszych europejskich stolic i miast pełnych historii, kultury i atrakcji turystycznych. W naszej ofercie znajdują się wycieczki do Pragi, Berlina, Paryża, Londynu czy Rzymu, a także wielu innych miejsc w całej Europie. Podczas podróży uczniowie poznają najciekawsze zabytki, odkryją lokalne legendy i kulturę oraz przeżyją niezapomniane chwile w międzynarodowej atmosferze. Z nami każde zwiedzanie staje się fascynującą lekcją historii i geografii w terenie.</li>
                    <li><b>Wycieczki tematyczne</b> – ucz się i baw z Biurem Podróży RAFA! Oprócz klasycznych programów oferujemy wyjątkowe wyjazdy tematyczne, które łączą naukę, aktywność i zabawę. Organizujemy wycieczki edukacyjne, podczas których uczniowie poszerzają wiedzę w praktyce, wyjazdy integracyjne budujące współpracę i przyjaźnie, a także pełne emocji spływy kajakowe i rajdy rowerowe. W programach nie brakuje także wizyt w parkach rozrywki i parkach tematycznych, które dostarczają niezapomnianych emocji i integrują grupę w atmosferze beztroskiej zabawy.</li>
                </ul>
                <div class="link"><a href="{{ route('packages') }}"> <i class="fas fa-arrow-circle-right"></i></a></div>
            </div>
            <div class="why">
                <div style="font-size: larger">
                    <b>Dlaczego warto wybrać Biuro Podróży RAFA?</b>
                </div>
                <p>Biuro Podróży RAFA to gwarancja udanej, bezpiecznej i perfekcyjnie zorganizowanej wycieczki. Od lat specjalizujemy się w organizacji wyjazdów szkolnych, integracyjnych oraz turystycznych po Polsce i Europie, dzięki czemu zyskaliśmy zaufanie setek szkół, instytucji i grup zorganizowanych. Naszą misją jest tworzenie podróży, które łączą edukację, rozrywkę i niezapomniane przygody.</p>
                <h6><b>Kompleksowa organizacja wycieczek</b></h6>
                <p>Z nami nie musisz martwić się o szczegóły. Zapewniamy wygodny transport, sprawdzone zakwaterowanie oraz doświadczonych przewodników i pilotów, którzy zadbają o każdy etap programu. Każda wycieczka przygotowywana jest kompleksowo – od planowania trasy, przez rezerwacje, aż po realizację.</p>
                <h6><b>Bezpieczeństwo i opieka</b></h6>
                <p>Dbamy o komfort i bezpieczeństwo uczestników. Współpracujemy wyłącznie z zaufanymi przewoźnikami i korzystamy z usług doświadczonych pilotów, którzy czuwają nad przebiegiem podróży. Dzięki temu nauczyciele i opiekunowie mogą w pełni cieszyć się wyjazdem razem z grupą.</p>
                <h6><b>Atrakcyjne ceny</b></h6>
                <p>W RAFA wierzymy, że podróżowanie powinno być dostępne dla każdego. Dlatego nasze programy dostosowujemy do różnych budżetów, zachowując przy tym wysoki standard usług. Oferujemy wycieczki, które łączą atrakcyjny program z korzystną ceną, co czyni naszą ofertę konkurencyjną i wyjątkowo korzystną.</p>
                <h6><b>Indywidualne podejście</b></h6>
                <p>Każda grupa jest inna, dlatego dopasowujemy program wycieczki do oczekiwań uczestników. Chcesz, aby wyjazd miał charakter edukacyjny, przyrodniczy, integracyjny, a może pełen rozrywki? Przygotujemy propozycję „szytą na miarę”, tak aby każdy uczestnik wrócił z wyjazdu z pięknymi wspomnieniami.</p>
                <h6><b>Główne korzyści z wyboru Biura Podróży RAFA</b></h6>
                <ul>
                    <li><b>Edukacja i rozrywka w jednym</b> – nasze wycieczki łączą poznawanie historii, kultury i przyrody z aktywną zabawą i integracją.</li>
                    <li><b>Doświadczenie i pasja</b> – od lat organizujemy podróże po całej Polsce i Europie. Kochamy to, co robimy, a nasza pasja do turystyki przekłada się na zadowolenie uczestników.</li>
                    <li><b>Łatwa rezerwacja</b> – wystarczy jeden telefon lub szybki kontakt online, aby zarezerwować wycieczkę. Proces rezerwacji jest prosty, szybki i przyjazny.</li>
                    <li><b>Wyjątkowe kierunki</b> – organizujemy wyjazdy do miast, gór, nad morze, do parków narodowych, parków rozrywki i tematycznych – wszędzie tam, gdzie czeka na Was przygoda.</li>
                </ul>
                <p>Biuro Podróży RAFA to więcej niż organizator – to partner w podróżowaniu. Naszym celem jest nie tylko zapewnienie atrakcyjnego programu, ale także stworzenie wyjątkowej atmosfery, dzięki której każda wycieczka staje się niezapomnianym doświadczeniem. Wybierając RAFA, wybierasz sprawdzoną jakość, bezpieczeństwo i wspomnienia, które zostają na lata.</p>
            </div>
        </div>

        <div class="third-box">
            <div class="outro"><p><div style="font-size: larger"><b>Skontaktuj się z nami!</b></div><p><br>
                    Zapraszamy do kontaktu z Biurem Podróży RAFA. Nasz zespół z chęcią pomoże w zaplanowaniu Twojej wymarzonej wycieczki szkolnej! Zadzwoń lub wyślij zapytanie, a my przygotujemy ofertę dopasowaną do Twoich potrzeb.<br><br><b>Zarezerwuj wycieczkę już dziś i twórz wspomnienia na całe życie!</b></p>
                <div class="link"><a href="{{ route('contact') }}"> <i class="fas fa-arrow-circle-right"></i></a></div></div>
        </div>
    </div>
    </div>
@endsection
