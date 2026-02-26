@extends('front.layout.master')

@section('main_content')
    <div class="page-top">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <div class="breadcrumb-container">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}">Start</a></li>
                            <li class="breadcrumb-item active">Dokumenty</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="documents-faq-head container pt_50">
        <h1>Dokumenty</h1>
    </div>

    <div class="documents-sections docs-centered">
        <div class="top-section">
                <div class="box-for-picture pb_10">
                <div class="insurance-picture" style="background-image: linear-gradient(to left, rgba(0,0,0,0.45) 20%, rgba(0,0,0,0.08) 60%), url({{ asset('storage/dokumenty.jpg') }});">
                    <div class="insurance-picture-space">
                        <div class="insurance-text-ad">
                            <p class="big">Tutaj znajdziesz wszystkie niezbędne dokumenty.</p>
                            <p class="small hide-mobile">Regulaminy, warunki i formularze, które ułatwią przygotowanie wycieczki.</p>
                            <p class="sub">Wszystko w jednym miejscu do pobrania.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container pt_40 pb_70">
            <div class="row">
                <div class="col-lg-6">
                    <div class="card p-4 mb-4">
                        <h3>Dokumenty</h3>
                        <ul class="list-unstyled mt-3">
                            <li class="mb-2"><a href="{{ asset('storage/dokumenty/Warunki-Uczestnictwa-2025.pdf') }}" class="doc-link" target="_blank" rel="noopener"><i class="fas fa-file-pdf" style="color:#d23f2d;margin-right:8px"></i>Warunki uczestnictwa w imprezach organizowanych przez Biuro Podróży RAFA – do umów zawartych od 01.01.2025</a></li>
                            <li class="mb-2"><a href="{{ asset('storage/dokumenty/Ustawa-1.pdf') }}" class="doc-link" target="_blank" rel="noopener"><i class="fas fa-file-pdf" style="color:#d23f2d;margin-right:8px"></i>Ustawa o imprezach turystycznych</a></li>
                            <li class="mb-2"><a href="{{ asset('storage/dokumenty/Standardowy-Formularz.pdf') }}" class="doc-link" target="_blank" rel="noopener"><i class="fas fa-file-pdf" style="color:#d23f2d;margin-right:8px"></i>Standardowy Formularz Informacyjny</a></li>
                            <li class="mb-2"><a href="{{ asset('storage/dokumenty/regulamin_przewozu_osób.pdf') }}" class="doc-link" target="_blank" rel="noopener"><i class="fas fa-file-pdf" style="color:#d23f2d;margin-right:8px"></i>Regulamin przewozu osób autokarem podczas wycieczek organizowanych przez Biuro Podróży RAFA</a></li>
                            <li class="mb-2"><a href="{{ asset('storage/dokumenty/bp_rafa_procedury.pdf') }}" class="doc-link" target="_blank" rel="noopener"><i class="fas fa-file-pdf" style="color:#d23f2d;margin-right:8px"></i>Procedury Ochrony Małoletnich/Dzieci Podczas Wycieczek</a></li>
                            <li class="mb-2"><a href="{{ asset('storage/dokumenty/polityka_rodo.pdf') }}" class="doc-link" target="_blank" rel="noopener"><i class="fas fa-file-pdf" style="color:#d23f2d;margin-right:8px"></i>Polityka RODO</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card p-4 mb-4">
                        <h3>O nas</h3>
                        <ul class="list-unstyled mt-3">
                            <li class="mb-2"><a href="{{ asset('storage/dokumenty/CEIDG-1.pdf') }}" class="doc-link" target="_blank" rel="noopener"><i class="fas fa-file-pdf" style="color:#d23f2d;margin-right:8px"></i>CEIDG</a></li>
                            <li class="mb-2"><a href="{{ asset('storage/dokumenty/Certyfikat-2024.pdf') }}" class="doc-link" target="_blank" rel="noopener"><i class="fas fa-file-pdf" style="color:#d23f2d;margin-right:8px"></i>Gwarancja ubezpieczeniowa</a></li>
                            <li class="mb-2"><a href="{{ asset('storage/dokumenty/wpis_do_rejestru_organizatorow.pdf') }}" class="doc-link" target="_blank" rel="noopener"><i class="fas fa-file-pdf" style="color:#d23f2d;margin-right:8px"></i>Wpis do rejestru Organizatorów Turystyki</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

<style>
    .banner { position: relative; width: 100vw; overflow: hidden; padding: 40px 0; }
    .banner-inner { position: relative; z-index: 2; }
    .banner-content { display:flex; gap:1.5rem; align-items:center; }
    .banner-image { flex: 0 0 46%; }
    .banner-image img { width:100%; border-radius:16px; box-shadow:0 12px 28px rgba(0,0,0,0.18); }
    .banner-details .details h2 { color:#0f1f3d; font-size:2.3rem; }
    .banner-details .lead { color:#2a2e36; margin-top:0.5rem; }

    .doc-link { color: #0f1f3d; text-decoration: none; }
    .doc-link:hover { text-decoration: underline; }

    .card { background: #fff; border-radius: 12px; box-shadow: 0 10px 20px rgba(0,0,0,0.06); }
    .doc-btn { display:inline-block; padding:0.5rem 1rem; background:#0f1f3d; color:#fff; border-radius:8px; text-decoration:none; }

    @media (max-width: 991.98px) {
        .banner-content { flex-direction: column; text-align:center; }
        .banner-image { max-width:100%; }
    }

    /* Page-specific adjustments for documents banner */
    .documents-sections .box-for-picture { max-width: 100%; }
    .documents-sections .insurance-picture {
        border-radius: 22px;
        background-size: cover;
        background-position: center;
        padding: 72px 48px;
        min-height: 320px;
        display: flex;
        align-items: center;
    }
    .documents-sections .insurance-picture-space {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: flex-end; /* tekst po prawej stronie */
    }
    .documents-sections .insurance-text-ad {
        color: #ffffff;
        /* blok stoi po prawej, ale tekst wewnątrz jest wyrównany do lewej */
        text-align: left;
        margin-left: auto;
        font-weight: 700;
        font-size: clamp(1.5rem, 2.6vw, 2.2rem);
        line-height: 1.6; /* większy odstęp pionowy */
        max-width: 48%;
        display: block;
        background: rgba(0,0,0,0.32);
        padding: 20px 26px;
        border-radius: 12px;
        box-shadow: 0 14px 40px rgba(0,0,0,0.28);
        text-shadow: 0 6px 18px rgba(0,0,0,0.45);
    }
    /* dodajemy niewielki odstęp między liniami/elementami w bloku */
    .documents-sections .insurance-text-ad br { line-height: 1.6; }

    .documents-sections .insurance-text-ad p { margin: 0 0 10px 0; }
    .documents-sections .insurance-text-ad p.big { font-size: 1.45rem; font-weight:700; }
    .documents-sections .insurance-text-ad p.small { font-size: 1.02rem; font-weight:600; opacity:0.95; }
    .documents-sections .insurance-text-ad p.sub { font-size: 0.98rem; font-weight:600; opacity:0.95; }
    .documents-sections .insurance-sub {
        color: rgba(255,255,255,0.95);
        text-align: right;
        margin-top: 10px;
        font-weight: 600;
    }
    .documents-sections .insurance-text-ad .hide-mobile { display: block; font-weight: 600; font-size: 1.05rem; opacity: 0.95; }

    /* zamiast button: biały tekst (wyłączamy domyślne czerwone tło) */
    .documents-sections .buy-button.plain {
        background: transparent !important;
        color: #ffffff !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin-left: 22px;
        font-size: 1.05rem;
        font-weight: 600;
        border-radius: 0 !important;
    }

    @media (max-width: 767.98px) {
        .documents-sections .insurance-picture {
            padding: 28px 20px;
            min-height: 140px;
        }
        .documents-sections .insurance-text-ad { max-width: 100%; font-size: 1.2rem; }
        .documents-sections .insurance-picture-space { justify-content: center; }
        .documents-sections .buy-button.plain { margin-left: 0; margin-top: 8px; }
        /* hide documents banner on small screens */
        .documents-sections .top-section { display: none; }
    }
</style>
