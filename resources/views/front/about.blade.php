@extends('front.layout.master')

@section('head')
    @include('front.partials.seo', [
        'pageTitle' => 'O nas | Wycieczki szkolne i wyjazdy firmowe – Biuro Podróży RAFA',
        'pageDescription' => 'Organizujemy wycieczki szkolne, zielone szkoły i wyjazdy firmowe z całej Polski. Poznaj zespół RAFA, nasz sposób pracy, standardy bezpieczeństwa i podejście do organizacji grup.',
        'canonical' => route('about.global'),
    ])
    <x-seo.json-ld :schemas="[
        \App\Support\Seo\SchemaBuilder::aboutPage(
            'O nas – Biuro Podróży RAFA',
            'Specjalista od wycieczek szkolnych i wyjazdów firmowych z całej Polski.',
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
    $hasStats = ! empty($org['founded_year']) || ! empty($org['trips_count']) || ! empty($org['schools_count']);
    $specializations = [
        [
            'icon' => 'fas fa-school',
            'title' => 'Wycieczki szkolne',
            'text' => 'Programy w Polsce i za granicą — dopasowane do wieku uczniów i celu wyjazdu.',
        ],
        [
            'icon' => 'fas fa-leaf',
            'title' => 'Zielone szkoły',
            'text' => 'Nauka, integracja i aktywny odpoczynek w jednym, przemyślanym programie.',
        ],
        [
            'icon' => 'fas fa-briefcase',
            'title' => 'Wyjazdy firmowe',
            'text' => 'Integracje i wyjazdy motywacyjne szyte pod potrzeby konkretnego zespołu.',
        ],
        [
            'icon' => 'fas fa-bus',
            'title' => 'Transport autokarowy',
            'text' => 'Sprawdzeni przewoźnicy i logistyka od A do Z — bez niespodzianek po drodze.',
        ],
    ];
    $trustItems = [
        [
            'icon' => 'fas fa-certificate',
            'title' => 'Licencja i TFG',
            'text' => 'Mamy licencję organizatora turystyki i wpis do rejestru TFG'
                .(! empty($org['license_number']) ? ' (nr '.$org['license_number'].')' : '')
                .'.',
        ],
        [
            'icon' => 'fas fa-comments-dollar',
            'title' => 'Jasna współpraca',
            'text' => 'Mówimy wprost, co zawiera cena i jak wygląda organizacja wyjazdu krok po kroku.',
        ],
        [
            'icon' => 'fas fa-user-check',
            'title' => 'Sprawdzony zespół',
            'text' => 'Współpracujemy z doświadczonymi pilotami, opiekunami i przewoźnikami.',
        ],
        [
            'icon' => 'fas fa-sliders-h',
            'title' => 'Program pod grupę',
            'text' => 'Budżet i program dopasowujemy do realnych potrzeb grupy — a nie odwrotnie.',
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
                        <p class="small hide-mobile">Wycieczki szkolne i wyjazdy firmowe z kompleksową obsługą.</p>
                        <p class="sub">Od pierwszego telefonu do bezpiecznego powrotu do domu.</p>
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
                    Cześć! Tu zespół Biura Podróży RAFA. Od lat organizujemy wycieczki szkolne, zielone szkoły
                    i wyjazdy firmowe. Dla nas to nie jest „kolejny projekt” — to konkretna grupa ludzi,
                    o której bezpieczeństwo i komfort dbamy od pierwszego telefonu do powrotu do domu.
                </p>
                <p>
                    Współpracujemy zarówno ze szkołami, które szukają sprawdzonego partnera do organizacji wyjazdu dla uczniów,
                    jak i z firmami planującymi integrację, wyjazd motywacyjny albo program szyty pod zespół.
                </p>
            </header>

            <section class="about-block" aria-labelledby="about-specializations">
                <div class="about-section-head">
                    <h2 id="about-specializations">Nasze specjalizacje</h2>
                    <p>Cztery obszary, w których czujemy się jak w domu — i w których masz pełne wsparcie organizacyjne.</p>
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

            <section class="about-block" aria-labelledby="about-trust">
                <div class="about-section-head">
                    <h2 id="about-trust">Dlaczego nam ufają</h2>
                    <p>Przejrzystość, formalności i ludzie, którzy naprawdę ogarniają wyjazd grupowy.</p>
                </div>
                <div class="about-card-grid">
                    @foreach($trustItems as $item)
                        <div class="about-card about-card--soft">
                            <div class="about-card-icon about-card-icon--soft" aria-hidden="true"><i class="{{ $item['icon'] }}"></i></div>
                            <h3>{{ $item['title'] }}</h3>
                            <p>{{ $item['text'] }}</p>
                        </div>
                    @endforeach
                </div>
                @if(!empty($org['tfg_info']))
                    <p class="about-note">{{ $org['tfg_info'] }}</p>
                @endif
            </section>

            @if($hasStats)
                <section class="about-block" aria-labelledby="about-stats">
                    <div class="about-section-head">
                        <h2 id="about-stats">RAFA w liczbach</h2>
                    </div>
                    <div class="about-stats">
                        @if(!empty($org['founded_year']))
                            <div class="about-stat">
                                <strong>{{ now()->year - (int) $org['founded_year'] }}+</strong>
                                <span>lat doświadczenia</span>
                            </div>
                        @endif
                        @if(!empty($org['trips_count']))
                            <div class="about-stat">
                                <strong>{{ $org['trips_count'] }}+</strong>
                                <span>zrealizowanych wyjazdów</span>
                            </div>
                        @endif
                        @if(!empty($org['schools_count']))
                            <div class="about-stat">
                                <strong>{{ $org['schools_count'] }}+</strong>
                                <span>obsłużonych szkół i firm</span>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            <section class="about-cta" aria-labelledby="about-contact">
                <div class="about-cta-copy">
                    <h2 id="about-contact">Porozmawiajmy o wyjeździe</h2>
                    <p>
                        Masz pomysł na wycieczkę szkolną albo wyjazd firmowy?
                        Napisz albo zadzwoń — przygotujemy propozycję bez zbędnego komplikowania.
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
        font-size: clamp(1.6rem, 3vw, 2.1rem);
        line-height: 1.1;
        margin-bottom: 6px;
    }

    .about-stat span {
        color: var(--about-muted);
        font-size: 0.95rem;
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

    @media (max-width: 991.98px) {
        .about-cta {
            grid-template-columns: 1fr;
        }

        .about-stats {
            grid-template-columns: 1fr;
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
