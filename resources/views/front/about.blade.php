@extends('front.layout.master')

@section('head')
    @include('front.partials.seo', [
        'pageTitle' => 'O nas | Wycieczki szkolne i wyjazdy grupowe – Biuro Podróży RAFA',
        'pageDescription' => 'Biuro Podróży RAFA to ogólnopolski organizator wycieczek szkolnych i wyjazdów grupowych od 2011 roku. Ponad 20 000 uczestników w 2025, ponad 11 000 wariantów ofert i kompleksowa realizacja wyjazdów w Polsce i Europie.',
        'canonical' => route('about.global'),
    ])
    <x-seo.json-ld :schemas="[
        \App\Support\Seo\SchemaBuilder::aboutPage(
            'O nas – Biuro Podróży RAFA',
            'Wyspecjalizowany organizator wycieczek szkolnych i wyjazdów grupowych od 2011 roku. Polska i Europa.',
            route('about.global')
        ),
        \App\Support\Seo\SchemaBuilder::faqPage($faqs),
    ]" />
@endsection

@section('main_content')
@php
    $org = \App\Models\SeoSetting::organization();
    $phoneDisplay = $org['phone'] ?? '+48 606 102 243';
    $phoneTel = preg_replace('/[^\d+]/', '', $phoneDisplay) ?: '+48606102243';
    $email = $org['email'] ?? 'rafa@bprafa.pl';
    $addressLine = trim(($org['street'] ?? 'Marii Konopnickiej 6').', '.($org['postal_code'] ?? '00-491').' '.($org['city'] ?? 'Warszawa'));

    $specializations = [
        [
            'icon' => 'fas fa-school',
            'title' => 'Wycieczki szkolne',
            'text' => 'Główny obszar naszej działalności — programy dopasowane do wieku uczniów, celów edukacyjnych i budżetu szkoły.',
        ],
        [
            'icon' => 'fas fa-users',
            'title' => 'Wyjazdy grupowe',
            'text' => 'Organizujemy także wyjazdy firmowe, integracyjne, szkoleniowe oraz inne wyjazdy dla grup zorganizowanych.',
        ],
        [
            'icon' => 'fas fa-plane',
            'title' => 'Polska i Europa',
            'text' => 'Wycieczki krajowe i zagraniczne — transportem autokarowym, kolejowym oraz lotniczym.',
        ],
        [
            'icon' => 'fas fa-user-friends',
            'title' => 'Miejsca dla opiekunów',
            'text' => 'Przy wycieczkach szkolnych zapewniamy bezpłatne miejsca dla opiekunów — standardowo 1 miejsce na 15 uczestników.',
        ],
    ];

    $variantItems = [
        [
            'icon' => 'fas fa-map-marked-alt',
            'title' => '55 miejscowości wyjazdu',
            'text' => 'Ten sam program może mieć wiele wariantów — w zależności od miejsca rozpoczęcia podróży i indywidualnej kalkulacji.',
        ],
        [
            'icon' => 'fas fa-sync-alt',
            'title' => 'Aktualizacja w czasie rzeczywistym',
            'text' => 'Każdy wariant jest automatycznie kalkulowany z uwzględnieniem miejsca wyjazdu oraz aktualnych kosztów realizacji.',
        ],
        [
            'icon' => 'fas fa-search',
            'title' => 'Samodzielne porównanie ofert',
            'text' => 'Klient może znaleźć i porównać aktualny wariant bez konieczności każdorazowego przygotowywania kalkulacji od podstaw.',
        ],
        [
            'icon' => 'fas fa-layer-group',
            'title' => 'Szeroka, aktualna oferta',
            'text' => 'Łączymy bardzo szeroki katalog wycieczek szkolnych z bieżącą aktualnością cen i warunków prezentowanych na stronie.',
        ],
    ];

    $qualityItems = [
        [
            'icon' => 'fas fa-clipboard-check',
            'title' => 'Jakość całego procesu',
            'text' => 'Od pierwszego kontaktu, przez przygotowanie i realizację wycieczki, aż po obsługę posprzedażową.',
        ],
        [
            'icon' => 'fas fa-star',
            'title' => 'Stałe podnoszenie standardów',
            'text' => 'Dbamy o poziom transportu, zakwaterowania, organizacji programu, obsługi grup oraz komunikacji z klientem.',
        ],
        [
            'icon' => 'fas fa-headset',
            'title' => 'Reakcja w sytuacjach awaryjnych',
            'text' => 'Dobry organizator jest przygotowany także na to, czego nie da się przewidzieć — i zapewnia wsparcie, gdy potrzebna jest szybka decyzja.',
        ],
        [
            'icon' => 'fas fa-shield-alt',
            'title' => 'Formalne bezpieczeństwo',
            'text' => 'Działamy jako organizator turystyki — z wymaganym wpisem do rejestru oraz gwarancją ubezpieczeniową organizatora.',
        ],
    ];

    $insuranceItems = [
        [
            'icon' => 'fas fa-home',
            'title' => 'Wycieczki krajowe',
            'text' => 'Ubezpieczenie NNW do 30 000 zł na osobę oraz Assistance — w ramach podstawowej oferty.',
        ],
        [
            'icon' => 'fas fa-globe-europe',
            'title' => 'Wycieczki zagraniczne',
            'text' => 'Koszty leczenia do 300 000 euro na osobę, ubezpieczenie chorób przewlekłych, transport medyczny bez limitu ceny oraz rozszerzenie dla zorganizowanych wyjazdów dzieci i młodzieży.',
        ],
    ];
@endphp

<div class="page-top">
    <div class="container">
        <div class="breadcrumb-container">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Start</a></li>
                <li class="breadcrumb-item active">O nas</li>
            </ol>
        </div>
    </div>
</div>

<div class="about-sections">
    <div class="top-section">
        <div class="box-for-picture pb_10">
            <div class="insurance-picture" style="background-image: linear-gradient(to left, rgba(0,0,0,0.52) 18%, rgba(0,0,0,0.12) 62%), url({{ asset('storage/dokumenty.jpg') }});">
                <div class="insurance-picture-space">
                    <div class="insurance-text-ad">
                        <p class="big">Biuro Podróży RAFA</p>
                        <p class="small hide-mobile">Wyspecjalizowany organizator wycieczek szkolnych i wyjazdów grupowych.</p>
                        <p class="sub">Od 2011 · Polska i Europa · 20&nbsp;000+ uczestników w 2025</p>
                        <div class="about-hero-actions">
                            <a href="{{ route('contact') }}" class="about-btn about-btn--primary">Skontaktuj się</a>
                            <a href="{{ route('packages') }}" class="about-btn about-btn--ghost">Zobacz ofertę</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <main class="container about-main">
        <article>
            <header class="about-intro">
                <h1>O nas</h1>
                <p class="about-lead">
                    Biuro Podróży RAFA to wyspecjalizowany, ogólnopolski organizator wycieczek szkolnych i wyjazdów grupowych.
                    Firma działa nieprzerwanie od 2011 roku i z każdym rokiem rozwija skalę działalności, ofertę oraz wykorzystywane technologie.
                    W 2025 roku wyjazdy organizowane przez Biuro Podróży RAFA objęły ponad 20&nbsp;000 uczestników.
                </p>
                <p>
                    Specjalizujemy się przede wszystkim w organizacji wycieczek szkolnych, ale organizujemy również inne rodzaje wyjazdów grupowych,
                    w tym wyjazdy firmowe, integracyjne, szkoleniowe oraz inne wyjazdy dla grup zorganizowanych.
                </p>
                <p>
                    RAFA jest organizatorem turystyki, a nie agentem turystycznym. Oznacza to, że kompleksowo przygotowujemy i realizujemy wyjazdy
                    — od przygotowania programu i kalkulacji, przez rezerwację transportu, zakwaterowania i biletów wstępu,
                    po realizację wycieczki i obsługę grupy podczas podróży. Wycieczki organizujemy na terenie Polski i Europy,
                    korzystając z transportu autokarowego, kolejowego oraz lotniczego.
                </p>
            </header>

            <section class="about-block" aria-labelledby="about-specializations">
                <div class="about-section-head">
                    <h2 id="about-specializations">Nasze specjalizacje</h2>
                    <p>Wycieczki szkolne to nasz główny obszar, ale kompleksowo obsługujemy też inne wyjazdy grupowe — w Polsce i Europie.</p>
                </div>
                <div class="about-card-grid">
                    @foreach($specializations as $item)
                        <div class="about-card">
                            <div class="about-card-icon" aria-hidden="true"><i class="{{ $item['icon'] }}"></i></div>
                            <h3>{{ $item['title'] }}</h3>
                            <p>{{ $item['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="about-block" aria-labelledby="about-nationwide">
                <div class="about-section-head">
                    <h2 id="about-nationwide">Wycieczki szkolne z całej Polski</h2>
                    <p>Siedziba w Warszawie, działalność ogólnopolska — wyjazdy z dowolnego miejsca w kraju.</p>
                </div>
                <p>
                    Siedziba Biura Podróży RAFA znajduje się w Warszawie, jednak działamy jako organizator ogólnopolski.
                    Organizujemy wycieczki szkolne z dowolnego miejsca w Polsce, dostosowując ofertę i kalkulację do miejsca rozpoczęcia podróży.
                    Szczególnie dużą część naszej działalności stanowią wyjazdy z Warszawy, ale obsługujemy również szkoły i grupy z innych regionów kraju.
                </p>
                <p>
                    Organizujemy wyjazdy zarówno dla mniejszych grup, jak i dla dużych grup szkolnych oraz innych grup zorganizowanych.
                    Programy wycieczek mogą być modyfikowane i dostosowywane do potrzeb konkretnej grupy, wieku uczestników,
                    oczekiwań szkoły oraz zakładanego budżetu.
                </p>
                <p class="about-note" style="margin-top: 0.25rem;">
                    W przypadku wycieczek szkolnych zapewniamy również bezpłatne miejsca dla opiekunów — standardowo 1 miejsce na 15 uczestników.
                </p>
            </section>

            <section class="about-block" aria-labelledby="about-variants">
                <div class="about-section-head">
                    <h2 id="about-variants">Ponad 11&nbsp;000 aktualnych wariantów wycieczek szkolnych</h2>
                    <p>
                        Jednym z najważniejszych elementów rozwoju Biura Podróży RAFA jest połączenie wieloletniego doświadczenia
                        w organizacji turystyki z zaawansowanymi rozwiązaniami technologicznymi.
                    </p>
                </div>
                <p>
                    Na naszej stronie internetowej dostępnych jest obecnie ponad 11&nbsp;000 wariantów ofert wycieczek szkolnych.
                    Klienci mogą wybierać spośród 55 miejscowości wyjazdu z całej Polski.
                </p>
                <p>
                    Tak duża liczba wariantów nie oznacza tysięcy przypadkowych lub powielonych programów.
                    Wynika z połączenia konkretnych programów wycieczek z różnymi miejscami rozpoczęcia podróży oraz indywidualnymi kalkulacjami.
                    Przykładowo, ten sam trzydniowy program wyjazdu do Zakopanego może być dostępny w różnych wariantach
                    w zależności od miejsca rozpoczęcia podróży.
                </p>
                <div class="about-card-grid" style="margin-top: 1.25rem;">
                    @foreach($variantItems as $item)
                        <div class="about-card about-card--soft">
                            <div class="about-card-icon about-card-icon--soft" aria-hidden="true"><i class="{{ $item['icon'] }}"></i></div>
                            <h3>{{ $item['title'] }}</h3>
                            <p>{{ $item['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="about-block" aria-labelledby="about-tech">
                <div class="about-section-head">
                    <h2 id="about-tech">Technologia wspierająca organizację wyjazdów</h2>
                    <p>Technologia ułatwia wybór oferty — za organizację i realizację odpowiada doświadczony organizator.</p>
                </div>
                <p>
                    Technologia jest ważnym elementem rozwoju RAFA, ale nie zastępuje doświadczenia organizatora.
                    Wykorzystujemy własne rozwiązania technologiczne do tworzenia, kalkulowania, aktualizowania i prezentowania ofert wycieczek szkolnych.
                </p>
                <p>
                    System pozwala łączyć programy wycieczek z miejscem rozpoczęcia podróży, automatycznie przeliczać koszty realizacji
                    i prezentować klientom aktualne warianty ofert. Dzięki temu oferta dostępna na stronie może być znacznie szersza
                    niż tradycyjny katalog wycieczek przygotowanych dla jednego miasta wyjazdu.
                </p>
                <p>
                    Technologia ułatwia klientowi wybór i porównanie ofert, natomiast za organizację i prawidłową realizację wyjazdu
                    odpowiada doświadczony organizator turystyki.
                </p>
            </section>

            <section class="about-block" aria-labelledby="about-quality">
                <div class="about-section-head">
                    <h2 id="about-quality">Jakość, bezpieczeństwo i obsługa</h2>
                    <p>Dbamy nie tylko o program wycieczki, ale o jakość całego procesu organizacji wyjazdu.</p>
                </div>
                <div class="about-card-grid">
                    @foreach($qualityItems as $item)
                        <div class="about-card">
                            <div class="about-card-icon" aria-hidden="true"><i class="{{ $item['icon'] }}"></i></div>
                            <h3>{{ $item['title'] }}</h3>
                            <p>{{ $item['text'] }}</p>
                        </div>
                    @endforeach
                </div>
                <p style="margin-top: 1.25rem; margin-bottom: 0;">
                    Szczególną uwagę zwracamy na sprawne reagowanie w sytuacjach nieprzewidzianych i awaryjnych.
                    Zapewniamy klientom wsparcie również wtedy, gdy podczas wyjazdu pojawi się problem wymagający szybkiej reakcji
                    i podjęcia odpowiednich decyzji organizacyjnych.
                </p>
            </section>

            <section class="about-block" aria-labelledby="about-insurance">
                <div class="about-section-head">
                    <h2 id="about-insurance">Ubezpieczenie uczestników</h2>
                    <p>
                        Wszystkie wycieczki organizowane przez Biuro Podróży RAFA — zarówno krajowe, jak i zagraniczne — są objęte ubezpieczeniem.
                        Uczestnicy otrzymują wysoki poziom ochrony już w ramach podstawowej oferty.
                    </p>
                </div>
                <div class="about-card-grid">
                    @foreach($insuranceItems as $item)
                        <div class="about-card about-card--soft">
                            <div class="about-card-icon about-card-icon--soft" aria-hidden="true"><i class="{{ $item['icon'] }}"></i></div>
                            <h3>{{ $item['title'] }}</h3>
                            <p>{{ $item['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="about-block" aria-labelledby="about-formal">
                <div class="about-section-head">
                    <h2 id="about-formal">Doświadczenie i formalne bezpieczeństwo</h2>
                    <p>Od 2011 roku łączymy doświadczenie w organizacji wyjazdów z nowoczesnymi rozwiązaniami technologicznymi.</p>
                </div>
                <p>
                    Działamy jako organizator turystyki, posiadając wymagany prawem wpis do właściwego rejestru
                    oraz gwarancję ubezpieczeniową organizatora turystyki
                    @if(!empty($org['license_number']))
                        (nr {{ $org['license_number'] }})
                    @endif.
                    Dokumenty potwierdzające status organizatora, warunki uczestnictwa, zasady bezpieczeństwa
                    oraz pozostałe informacje formalne udostępniamy na naszej stronie internetowej.
                </p>
                @if(!empty($org['tfg_info']))
                    <p class="about-note">{{ $org['tfg_info'] }}</p>
                @endif
                <p style="margin-bottom: 0;">
                    Naszym celem jest połączenie doświadczenia, skali działalności, nowoczesnej technologii,
                    wysokiej jakości organizacji oraz bezpieczeństwa uczestników.
                </p>
            </section>

            <section class="about-block" aria-labelledby="about-stats">
                <div class="about-section-head">
                    <h2 id="about-stats">RAFA w liczbach</h2>
                    <p>Biuro Podróży RAFA — wyspecjalizowany organizator wycieczek szkolnych i wyjazdów grupowych.</p>
                </div>
                <div class="about-stats about-stats--five">
                    <div class="about-stat">
                        <strong>2011</strong>
                        <span>rok rozpoczęcia działalności</span>
                    </div>
                    <div class="about-stat">
                        <strong>20&nbsp;000+</strong>
                        <span>uczestników wyjazdów w 2025</span>
                    </div>
                    <div class="about-stat">
                        <strong>11&nbsp;000+</strong>
                        <span>aktualnych wariantów wycieczek</span>
                    </div>
                    <div class="about-stat">
                        <strong>55</strong>
                        <span>miejscowości wyjazdu w systemie</span>
                    </div>
                    <div class="about-stat">
                        <strong>PL + EU</strong>
                        <span>kierunki organizowanych wyjazdów</span>
                    </div>
                </div>
            </section>

            <section class="about-cta" aria-labelledby="about-contact">
                <div class="about-cta-copy">
                    <h2 id="about-contact">Porozmawiajmy o wyjeździe</h2>
                    <p>
                        Szukasz wycieczki szkolnej lub wyjazdu grupowego?
                        Napisz albo zadzwoń — przygotujemy propozycję dopasowaną do miejsca wyjazdu, grupy i budżetu.
                    </p>
                    <ul class="about-contact-list">
                        <li>
                            <i class="fas fa-phone" aria-hidden="true"></i>
                            <a href="tel:{{ $phoneTel }}">{{ $phoneDisplay }}</a>
                        </li>
                        <li>
                            <i class="fas fa-envelope" aria-hidden="true"></i>
                            <a href="mailto:{{ $email }}">{{ $email }}</a>
                        </li>
                        <li>
                            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                            <span>{{ $addressLine }}</span>
                        </li>
                    </ul>
                </div>
                <div class="about-cta-actions">
                    <a href="{{ route('contact') }}" class="about-btn about-btn--primary">Formularz kontaktowy</a>
                    <a href="{{ route('guide.global') }}" class="about-btn about-btn--secondary">Poradnik przed wyjazdem</a>
                    <a href="{{ route('packages') }}" class="about-btn about-btn--ghost-dark">Oferta wycieczek</a>
                </div>
            </section>

            <section class="about-guide" aria-labelledby="about-guide">
                <div>
                    <h2 id="about-guide">Poradnik przed wyjazdem</h2>
                    <p>
                        Jeśli dopiero planujesz wycieczkę, zajrzyj do poradnika — praktyczne wskazówki
                        o organizacji, ubezpieczeniu, dokumentach i komunikacji z rodzicami lub zespołem.
                    </p>
                </div>
                <a href="{{ route('guide.global') }}" class="about-btn about-btn--secondary">Przejdź do poradnika</a>
            </section>

            @if(isset($faqs) && $faqs->isNotEmpty())
                <div class="about-faq">
                    <x-seo.faq-section :faqs="$faqs" title="Najczęstsze pytania o Biuro Podróży RAFA" idPrefix="about-faq" />
                </div>
            @endif
        </article>
    </main>
</div>

<style>
    .about-sections {
        --about-ink: #0f1f3d;
        --about-muted: #5b6573;
        --about-line: #e6e8ec;
        --about-soft: #f7f9fc;
        --about-accent: #ce0d0d;
        --about-accent-dark: #af0b0b;
        padding-bottom: 100px;
    }

    .about-sections .box-for-picture { max-width: 100%; }

    .about-sections .insurance-picture {
        border-radius: 22px;
        background-size: cover;
        background-position: center;
        padding: 72px 48px;
        min-height: 320px;
        display: flex;
        align-items: center;
    }

    .about-sections .insurance-picture-space {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }

    .about-sections .insurance-text-ad {
        color: #ffffff;
        text-align: left;
        margin-left: auto;
        font-weight: 700;
        line-height: 1.55;
        max-width: 48%;
        display: block;
        background: rgba(0, 0, 0, 0.34);
        padding: 22px 26px;
        border-radius: 12px;
        box-shadow: 0 14px 40px rgba(0, 0, 0, 0.28);
        text-shadow: 0 6px 18px rgba(0, 0, 0, 0.45);
    }

    .about-sections .insurance-text-ad p { margin: 0 0 10px 0; }
    .about-sections .insurance-text-ad p.big { font-size: 1.5rem; font-weight: 700; }
    .about-sections .insurance-text-ad p.small,
    .about-sections .insurance-text-ad .hide-mobile {
        font-size: 1.05rem;
        font-weight: 600;
        opacity: 0.95;
    }
    .about-sections .insurance-text-ad p.sub {
        font-size: 0.98rem;
        font-weight: 600;
        opacity: 0.92;
        margin-bottom: 18px;
    }

    .about-hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 4px;
    }

    .about-main {
        padding-top: 40px;
        max-width: 1100px;
    }

    .about-intro,
    .about-block,
    .about-guide,
    .about-faq {
        background: #fff;
        border: 1px solid var(--about-line);
        border-radius: 16px;
        padding: 28px 26px;
        margin-bottom: 22px;
        box-shadow: 0 10px 24px rgba(15, 31, 61, 0.05);
    }

    .about-intro h1,
    .about-section-head h2,
    .about-cta h2,
    .about-guide h2 {
        color: var(--about-ink);
        font-weight: 700;
        margin-bottom: 0.65rem;
    }

    .about-lead {
        font-size: 1.08rem;
        line-height: 1.7;
        color: var(--about-ink);
        margin-bottom: 0.9rem;
    }

    .about-intro p,
    .about-block > p,
    .about-section-head p,
    .about-card p,
    .about-guide p,
    .about-cta p {
        color: var(--about-muted);
        line-height: 1.65;
        margin-bottom: 0.75rem;
    }

    .about-section-head {
        margin-bottom: 1.25rem;
    }

    .about-section-head p { margin-bottom: 0; max-width: 46rem; }

    .about-card-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .about-card {
        background: var(--about-soft);
        border: 1px solid var(--about-line);
        border-radius: 14px;
        padding: 20px 18px;
        height: 100%;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .about-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(15, 31, 61, 0.08);
    }

    .about-card--soft {
        background: #fff;
    }

    .about-card h3 {
        color: var(--about-ink);
        font-size: 1.05rem;
        font-weight: 700;
        margin: 0 0 0.45rem;
    }

    .about-card p { margin-bottom: 0; font-size: 0.96rem; }

    .about-card-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(206, 13, 13, 0.1);
        color: var(--about-accent);
        margin-bottom: 12px;
        font-size: 1.05rem;
    }

    .about-card-icon--soft {
        background: rgba(15, 31, 61, 0.08);
        color: var(--about-ink);
    }

    .about-note {
        margin: 1rem 0 0;
        color: var(--about-muted);
        font-size: 0.92rem;
    }

    .about-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }

    .about-stats--five {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }

    .about-stat {
        text-align: center;
        background: var(--about-soft);
        border: 1px solid var(--about-line);
        border-radius: 14px;
        padding: 22px 14px;
    }

    .about-stat strong {
        display: block;
        color: var(--about-accent);
        font-size: clamp(1.35rem, 2.4vw, 1.85rem);
        line-height: 1.15;
        margin-bottom: 6px;
    }

    .about-stat span {
        color: var(--about-muted);
        font-size: 0.9rem;
    }

    .about-cta {
        display: grid;
        grid-template-columns: 1.4fr 0.9fr;
        gap: 24px;
        align-items: center;
        background: linear-gradient(135deg, #0f1f3d 0%, #1a335c 100%);
        color: #fff;
        border-radius: 18px;
        padding: 30px 28px;
        margin-bottom: 22px;
        box-shadow: 0 16px 36px rgba(15, 31, 61, 0.22);
    }

    .about-cta h2,
    .about-cta p,
    .about-cta a,
    .about-cta span {
        color: #fff;
    }

    .about-cta p { opacity: 0.92; }

    .about-contact-list {
        list-style: none;
        padding: 0;
        margin: 1rem 0 0;
    }

    .about-contact-list li {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 0.55rem;
        line-height: 1.45;
    }

    .about-contact-list i {
        width: 18px;
        margin-top: 3px;
        opacity: 0.9;
    }

    .about-contact-list a {
        text-decoration: none;
        border-bottom: 1px solid rgba(255, 255, 255, 0.35);
    }

    .about-contact-list a:hover {
        border-bottom-color: #fff;
    }

    .about-cta-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .about-guide {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
    }

    .about-guide p { margin-bottom: 0; max-width: 40rem; }

    .about-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0.7rem 1.15rem;
        border-radius: 10px;
        font-weight: 600;
        text-decoration: none !important;
        transition: transform 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease, color 0.2s ease;
        border: 1px solid transparent;
        text-align: center;
        line-height: 1.2;
    }

    .about-btn:hover { transform: translateY(-1px); }

    .about-btn--primary {
        background: var(--about-accent);
        color: #fff !important;
        box-shadow: 0 8px 18px rgba(206, 13, 13, 0.28);
    }

    .about-btn--primary:hover {
        background: var(--about-accent-dark);
        color: #fff !important;
    }

    .about-btn--secondary {
        background: #fff;
        color: var(--about-ink) !important;
        border-color: var(--about-line);
    }

    .about-btn--secondary:hover {
        border-color: #cfd5de;
        box-shadow: 0 8px 18px rgba(15, 31, 61, 0.08);
    }

    .about-btn--ghost {
        background: rgba(255, 255, 255, 0.12);
        color: #fff !important;
        border-color: rgba(255, 255, 255, 0.35);
        text-shadow: none;
    }

    .about-btn--ghost:hover {
        background: rgba(255, 255, 255, 0.2);
        color: #fff !important;
    }

    .about-btn--ghost-dark {
        background: transparent;
        color: #fff !important;
        border-color: rgba(255, 255, 255, 0.35);
    }

    .about-btn--ghost-dark:hover {
        background: rgba(255, 255, 255, 0.12);
        color: #fff !important;
    }

    .about-faq .faq-area { padding-top: 0; }
    .about-faq .section-title { margin-bottom: 1.2rem !important; }
    .about-faq .section-title h2 { color: var(--about-ink); font-weight: 700; }

    @media (max-width: 1100px) {
        .about-stats--five {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 991.98px) {
        .about-cta {
            grid-template-columns: 1fr;
        }

        .about-stats,
        .about-stats--five {
            grid-template-columns: 1fr 1fr;
        }

        .about-guide {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    @media (max-width: 767.98px) {
        .about-sections .insurance-picture {
            padding: 28px 20px;
            min-height: 180px;
        }

        .about-sections .insurance-text-ad {
            max-width: 100%;
            margin-left: 0;
        }

        .about-sections .insurance-picture-space {
            justify-content: center;
        }

        .about-card-grid {
            grid-template-columns: 1fr;
        }

        .about-stats,
        .about-stats--five {
            grid-template-columns: 1fr;
        }

        .about-intro,
        .about-block,
        .about-guide,
        .about-faq,
        .about-cta {
            padding: 22px 18px;
        }
    }
</style>
@endsection
