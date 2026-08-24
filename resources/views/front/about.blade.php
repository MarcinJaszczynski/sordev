@extends('front.layout.master')

@section('head')
    @include('front.partials.seo', [
        'pageTitle' => 'O nas – Biuro Podróży RAFA | Wycieczki szkolne i wyjazdy firmowe',
        'pageDescription' => 'Poznaj Biuro Podróży RAFA – specjalistę od wycieczek szkolnych, zielonych szkół i wyjazdów integracyjnych dla firm. Doświadczenie, licencja TFG, transport autokarowy i kompleksowa obsługa grup.',
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

<main class="container pt_50 pb_70">
    <article class="about-page">
        <header class="mb-5">
            <h1>Biuro Podróży RAFA — organizator wycieczek szkolnych i wyjazdów firmowych</h1>
            <p class="lead mt-3">
                Od lat specjalizujemy się w kompleksowej organizacji wyjazdów dla szkół, przedszkoli i firm z całej Polski.
                Planujemy wycieczki edukacyjne, zielone szkoły, wyjazdy integracyjne i eventy firmowe — z transportem autokarowym,
                noclegami, programem, pilotem i ubezpieczeniem w jednym miejscu.
            </p>
        </header>

        <section class="mb-5" aria-labelledby="about-who">
            <h2 id="about-who">Kim jesteśmy</h2>
            <p>
                Biuro Podróży RAFA to polski organizator turystyki grupowej z siedzibą w Warszawie.
                Obsługujemy szkoły podstawowe i średnie, samorządy, placówki oświatowe oraz firmy szukające sprawdzonego partnera
                do wyjazdów służbowych i integracyjnych. Każdy wyjazd traktujemy jak projekt — od pierwszego zapytania,
                przez umowę i dokumentację, po realizację w terenie z pilotem i koordynatorem.
            </p>
        </section>

        <section class="mb-5" aria-labelledby="about-specializations">
            <h2 id="about-specializations">Nasze specjalizacje</h2>
            <div class="row g-4">
                <div class="col-md-6">
                    <h3>Wycieczki szkolne</h3>
                    <p>Jedno- i wielodniowe wyjazdy edukacyjne po Polsce i Europie. Programy dopasowane do wieku uczniów i podstawy programowej.</p>
                </div>
                <div class="col-md-6">
                    <h3>Zielone szkoły</h3>
                    <p>Wielodniowe wyjazdy z noclegiem, wyżywieniem i atrakcjami edukacyjnymi — Bieszczady, Tatry, morze, Mazury.</p>
                </div>
                <div class="col-md-6">
                    <h3>Wyjazdy firmowe</h3>
                    <p>Integracje, wyjazdy studyjne, eventy dla zespołów — elastyczny program, transport, hotel i opieka koordynatora.</p>
                </div>
                <div class="col-md-6">
                    <h3>Transport autokarowy</h3>
                    <p>Współpraca ze sprawdzonymi przewoźnikami. Nowoczesne autokary z klimatyzacją, selekcja pojazdów pod wielkość grupy.</p>
                </div>
            </div>
        </section>

        <section class="mb-5" aria-labelledby="about-trust">
            <h2 id="about-trust">Dlaczego nam ufają</h2>
            <ul>
                <li>Licencja organizatora turystyki i wpis do rejestru TFG{{ !empty($org['license_number']) ? ' (nr '.$org['license_number'].')' : '' }}</li>
                <li>Ubezpieczenie uczestników w cenie wycieczki szkolnej</li>
                <li>Doświadczeni piloci i koordynatorzy wyjazdów</li>
                <li>Przejrzyste warunki uczestnictwa i ceny bez ukrytych kosztów</li>
                <li>Obsługa grup z całej Polski — wyjazdy z wielu miast</li>
                <li>Portal klienta i dokumentacja online dla nauczycieli i opiekunów</li>
            </ul>
            @if(!empty($org['tfg_info']))
                <p class="text-muted">{{ $org['tfg_info'] }}</p>
            @endif
        </section>

        @if(!empty($org['founded_year']) || !empty($org['trips_count']) || !empty($org['schools_count']))
            <section class="mb-5" aria-labelledby="about-stats">
                <h2 id="about-stats">RAFA w liczbach</h2>
                <div class="row text-center g-3">
                    @if(!empty($org['founded_year']))
                        <div class="col-md-4"><strong>{{ now()->year - (int) $org['founded_year'] }}+</strong><br>lat doświadczenia</div>
                    @endif
                    @if(!empty($org['trips_count']))
                        <div class="col-md-4"><strong>{{ $org['trips_count'] }}+</strong><br>zrealizowanych wyjazdów</div>
                    @endif
                    @if(!empty($org['schools_count']))
                        <div class="col-md-4"><strong>{{ $org['schools_count'] }}+</strong><br>szkół i firm</div>
                    @endif
                </div>
            </section>
        @endif

        <section class="mb-5" aria-labelledby="about-contact">
            <h2 id="about-contact">Skontaktuj się z nami</h2>
            <p>
                Zapraszamy do kontaktu — pomożemy zaplanować wycieczkę szkolną lub wyjazd firmowy dopasowany do Twojej grupy.
            </p>
            <p>
                <strong>Telefon:</strong> <a href="tel:606102243">{{ $org['phone'] ?? '+48 606 102 243' }}</a><br>
                <strong>E-mail:</strong> <a href="mailto:{{ $org['email'] ?? 'rafa@bprafa.pl' }}">{{ $org['email'] ?? 'rafa@bprafa.pl' }}</a><br>
                <strong>Adres:</strong> {{ $org['street'] ?? 'Marii Konopnickiej 6' }}, {{ $org['postal_code'] ?? '00-491' }} {{ $org['city'] ?? 'Warszawa' }}
            </p>
            <p><a href="{{ route('contact') }}" class="btn btn-primary">Formularz kontaktowy</a></p>
        </section>

        @if(isset($faqs) && $faqs->isNotEmpty())
            <x-seo.faq-section :faqs="$faqs" title="Najczęstsze pytania o Biuro Podróży RAFA" idPrefix="about-faq" />
        @endif
    </article>
</main>
@endsection
