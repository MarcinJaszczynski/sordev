<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale() ?? 'pl') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="content-language" content="pl">
    <meta name="robots" content="index,follow">
    <meta name="author" content="Biuro Podróży RAFA">
    <meta name="application-name" content="Biuro Podróży RAFA">
    <meta name="apple-mobile-web-app-title" content="Biuro Podróży RAFA">
    <meta name="theme-color" content="#0d3b66">
    <meta property="og:locale" content="pl_PL">
    <meta property="og:site_name" content="Biuro Podróży RAFA">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
        <!-- Google Consent Mode v2: set default denied BEFORE any tags -->
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){ dataLayer.push(arguments); }
                gtag('consent', 'default', {
                    'ad_storage': 'denied',
                    'analytics_storage': 'denied',
                    'ad_user_data': 'denied',
                    'ad_personalization': 'denied',
                    'wait_for_update': 500
                });
            </script>

        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','GTM-WNP7LFQC');</script>
        <!-- End Google Tag Manager -->
    @php
        $defaultTitle = 'Biuro Podróży RAFA – wycieczki szkolne i wyjazdy firmowe';
        $defaultDescription = 'Biuro Podróży RAFA organizuje wycieczki szkolne, zielone szkoły i wyjazdy integracyjne dla firm w całej Polsce i Europie. Sprawdź ofertę turystyki szkolnej i wyjazdów na zamówienie.';
        $baseKeywords = [
            'biuro podróży rafa',
            'wycieczki szkolne',
            'turystyka szkolna',
            'wyjazdy firmowe',
            'zielone szkoły',
            'wyjazdy integracyjne',
            'bprafa.pl'
        ];
        $defaultKeywords = implode(', ', $baseKeywords);
        $defaultCanonical = request()->getUri();
        $defaultOgImage = asset('uploads/logo.png');
        $siteUrl = config('app.url') ?: request()->getSchemeAndHttpHost();
        $organizationSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'TravelAgency',
            'name' => 'Biuro Podróży RAFA',
            'url' => $siteUrl,
            'sameAs' => [
                'https://bprafa.pl',
                'https://www.facebook.com/biuropodrozyrafa/',
                'https://www.instagram.com/biuropodrozyrafa/'
            ],
            'logo' => $defaultOgImage,
            'description' => $defaultDescription,
            'telephone' => '+48 606 102 243',
            'email' => 'mailto:rafa@bprafa.pl',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Marii Konopnickiej 6',
                'addressLocality' => 'Warszawa',
                'postalCode' => '00-491',
                'addressCountry' => 'PL'
            ],
            'areaServed' => [
                ['@type' => 'AdministrativeArea', 'name' => 'Polska']
            ],
            'openingHoursSpecification' => [
                [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday'],
                    'opens' => '09:00',
                    'closes' => '17:00'
                ]
            ]
        ];
    @endphp

    @hasSection('head')
        @yield('head')
    @else
        <title>{{ $defaultTitle }}</title>
        <meta name="description" content="{{ $defaultDescription }}">
        <meta name="keywords" content="{{ $defaultKeywords }}">
        <link rel="canonical" href="{{ $defaultCanonical }}">
        <meta property="og:title" content="{{ $defaultTitle }}">
        <meta property="og:description" content="{{ $defaultDescription }}">
        <meta property="og:url" content="{{ $defaultCanonical }}">
        @if($defaultOgImage)
            <meta property="og:image" content="{{ $defaultOgImage }}">
            <meta name="twitter:image" content="{{ $defaultOgImage }}">
        @endif
        <meta name="twitter:title" content="{{ $defaultTitle }}">
        <meta name="twitter:description" content="{{ $defaultDescription }}">
    @endif

    <script type="application/ld+json">
        {!! json_encode($organizationSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
    </script>


    <link rel="icon" type="image/ico" href="{{ asset('uploads/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- All CSS -->
    <link rel="stylesheet" href="{{ asset('dist-front/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('dist-front/css/bootstrap-datepicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('dist-front/css/animate.min.css') }}">
    <link rel="stylesheet" href="{{ asset('dist-front/css/magnific-popup.css') }}">
    <link rel="stylesheet" href="{{ asset('dist-front/css/owl.carousel.min.css') }}">
    <link rel="stylesheet" href="{{ asset('dist-front/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('dist-front/css/select2-bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('dist-front/css/all.css') }}">
    <link rel="stylesheet" href="{{ asset('dist-front/css/meanmenu.css') }}">
    <link rel="stylesheet" href="{{ asset('dist-front/css/spacing.css') }}">
    <link rel="stylesheet" href="{{ asset('dist-front/css/style.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('dist-front/css/front-directory-mobile.css') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans:ital,wght@0,100..900;1,100..900&display=swap">
    <link rel="stylesheet" href="{{ asset('dist-front/css/badges-fix.css') }}">

    <!-- Quick override: ensure multiline price expands card height (debugging/fallback) -->
    <style>
        /* force the multiline price block to behave as normal flow and let parents grow */
        .carousel .card-wrapper .card .card-body .card-text #price-accent .price-multiline,
        .card-wrapper .card .card-text #price-accent .price-multiline {
            display: block !important;
            height: auto !important;
            width: auto !important;
            box-sizing: border-box !important;
            overflow: visible !important;
            white-space: normal !important;
            /* center horizontally and add small lateral padding */
            margin: 0.25rem auto !important;
            padding: 0 0.5rem !important;
            text-align: center !important;
            align-self: center !important;
        }

        .card-wrapper .card,
        .carousel .card {
            min-height: 0 !important;
            height: auto !important;
        }

        .card-wrapper .card .card-text {
            height: auto !important;
            overflow: visible !important;
            align-items: center !important;
            flex-wrap: nowrap !important;
            padding-left: 0.25rem !important; /* small internal spacing */
            padding-right: 0.25rem !important;
        }
    </style>

    <!-- All Javascripts -->
    <script src="{{ asset('dist-front/js/jquery-3.6.1.min.js') }}"></script>
    <script src="{{ asset('dist-front/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('dist-front/js/bootstrap-datepicker.min.js') }}"></script>
    <script src="{{ asset('dist-front/js/jquery.magnific-popup.min.js') }}"></script>
    <script src="{{ asset('dist-front/js/owl.carousel.min.js') }}"></script>
    <script src="{{ asset('dist-front/js/wow.min.js') }}"></script>
    <script src="{{ asset('dist-front/js/select2.full.js') }}"></script>
    <script src="{{ asset('dist-front/js/jquery.waypoints.min.js') }}"></script>
    <script src="{{ asset('dist-front/js/moment.min.js') }}"></script>
    <script src="{{ asset('dist-front/js/counterup.min.js') }}"></script>
    <script src="{{ asset('dist-front/js/multi-countdown.js') }}"></script>
    <script src="{{ asset('dist-front/js/jquery.meanmenu.js') }}"></script>
    <script src="https://kit.fontawesome.com/758b8e5b95.js" crossorigin="anonymous"></script>

    @php
        $current_region_id = (request()->region_id ?? request()->cookie('region_id', 16));
    @endphp

    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-WNP7LFQC"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
{{-- <div class="top">
    <div class="container">
        <div class="row">
            <div class="col-md-6 left-side">
                <ul>
                    <li class="phone-text"><i class="fas fa-phone"></i> +48 606 102 243</li>
                    <li class="email-text"><i class="fas fa-envelope"></i> rafa@bprafa.pl</li>
                </ul>
            </div>
            <div class="col-md-6 right-side">
                <ul class="right">
                    <ul class="social">
                        <li><a style="font-size: 20px" href="https://www.facebook.com/biuropodrozyrafa/" target="_blank"><i class="fab fa-facebook-f"></i></a></li>
                        <li><a style="font-size: 20px" href="https://www.instagram.com/biuropodrozyrafa/" target="_blank"><i class="fab fa-instagram"></i></a></li>
                        <li class="menu">
                        <a href="{{ route('login') }}"><i class="fas fa-sign-in-alt"></i> Logowanie</a>
                    </li>
                    <li class="menu">
                        <a href="{{ route('registration') }}"><i class="fas fa-user"></i> Rejestracja</a>
                    </li> --}}
                </ul>
                </ul>
            </div>
        </div>
    </div>
</div>

@include('front.layout.nav')

@yield('main_content')

<div class="footer pt_70">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-md-4">
                <div class="item pb_50">
                    <h2 class="heading">Na skróty:</h2>
                    <ul class="useful-links">
                        <li><a href="{{ route('home')}}"><i class="fas fa-angle-right"></i> Strona główna</a></li>
                        <li><a href="{{ route('packages') }}"><i class="fas fa-angle-right"></i> Pełna oferta wycieczek 2024/2025</a></li>
                        <li><a href="{{ route('blog.global')}}"><i class="fas fa-angle-right"></i> Aktualności</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4 col-md-4">
                <div class="item pb_50">
                    <h2 class="heading">Przydatne linki:</h2>
                    <ul class="useful-links">
                        <li><a href="{{ route('documents.global') }}"><i class="fas fa-angle-right"></i> Dokumenty</a></li>
                        <li><a href="{{ route('insurance') }}"><i class="fas fa-angle-right"></i> Ubezpieczenia</a></li>
                        <li><a href="{{ route('contact') }}"><i class="fas fa-angle-right"></i> Kontakt</a></li>
                    </ul>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="item pb_50">
                    <h2 class="heading">Kontakt</h2>
                    <div class="list-item">
                        <div class="left">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="right">
                            Marii Konopnickiej 6, 00-491 Warszawa
                        </div>
                    </div>
                    <div class="list-item">
                        <div class="left">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="right"><a href="mailto:rafa@bprafa.pl"style="all:unset ">rafa@bprafa.pl</a></div>
                    </div>
                    <div class="list-item">
                        <div class="left">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="right"><a href="tel:606102243" style="all:unset ">+48 606 102 243</a></div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="footer-bottom">
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12">
                <div class="copyright">
                    Copyright &copy; 2024, Biuro Podróży RAFA. All Rights Reserved.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="scroll-top">
    <i class="fas fa-angle-up"></i>
</div>


<script src="{{ asset('dist-front/js/custom.js') }}"></script>

    <script>
        // Remove duplicate .scroll-top elements if any (keep first)
        document.addEventListener('DOMContentLoaded', function(){
            try{
                var els = document.querySelectorAll('.scroll-top');
                if(els && els.length > 1){
                    for(var i = 1; i < els.length; i++){
                        els[i].parentNode && els[i].parentNode.removeChild(els[i]);
                    }
                }
            }catch(e){}
        });
    </script>

@if(config('services.turnstile.site_key'))
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif

@stack('scripts')

@if($errors->any())
    @foreach ($errors->all() as $error)
        <script>
            console.error('{{ $error }}');
            // Można dodać własny system powiadomień
        </script>
    @endforeach
@endif

@if(session('success'))
    <script>
        console.log('{{ session("success") }}');
        // Można dodać własny system powiadomień
    </script>
@endif

@if(session('error'))
    <script>
        console.error('{{ session("error") }}');
        // Można dodać własny system powiadomień
    </script>
@endif

<!-- Cookie & Privacy Consent (Google Consent Mode v2 compatible) -->
<style>
    .cookie-banner{position:fixed;left:0;right:0;bottom:0;z-index:13000;background:#fff;border-top:1px solid #e5e7eb;box-shadow:0 -4px 24px rgba(0,0,0,.08);padding:14px 16px;font-family:inherit;max-width:100vw;box-sizing:border-box;overflow-x:hidden}
        .cookie-inner{max-width:1100px;margin:0 auto;display:flex;gap:12px;align-items:flex-start;box-sizing:border-box}
        .cookie-text{flex:1;font-size:14px;line-height:1.45;color:#111;min-width:0;word-wrap:break-word}
        .cookie-text a{color:#0d6efd;text-decoration:underline}
        .cookie-actions{display:flex;gap:8px;flex-wrap:wrap;flex-shrink:0}
        .cookie-btn{border:1px solid #d1d5db;background:#fff;color:#111;border-radius:6px;padding:8px 12px;font-size:14px;cursor:pointer;white-space:nowrap}
        .cookie-btn.primary{background:#111;color:#fff;border-color:#111}
        .cookie-settings{display:none;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:10px;margin-top:8px}
        .cookie-setting{display:flex;align-items:center;gap:8px;margin:6px 0}
    .cookie-manage{position:fixed;right:12px;left:auto;bottom:72px;background:#fff;border:1px solid #e5e7eb;border-radius:20px;padding:6px 10px;font-size:12px;cursor:pointer;z-index:13001 !important;box-shadow:0 2px 8px rgba(0,0,0,.08);max-width:calc(100vw - 24px);box-sizing:border-box;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;touch-action:manipulation}
    @media (max-width: 640px){.cookie-inner{flex-direction:column}.cookie-actions{justify-content:flex-start}.cookie-banner{padding:12px 10px}.cookie-text{font-size:13px}.cookie-manage{right:8px;bottom:64px;font-size:11px;padding:5px 8px;max-width:calc(100vw - 16px)}}
        .hidden{display:none!important}
        .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
        /* FORCE: ensure cookie manage/button is fixed and not clipped by any ancestor */
        #cookie-manage {
            position: fixed !important;
            right: 12px !important;
            left: auto !important;
            bottom: 92px !important; /* lift above scroll-top */
            z-index: 20000 !important;
            max-width: calc(100vw - 24px) !important;
            box-sizing: border-box !important;
            transform: none !important;
            overflow: visible !important;
            touch-action: manipulation !important;
        }
        /* make sure cookie banner floats above mobile menu */
        .cookie-banner {
            z-index: 19999 !important;
        }
        /* Allow titles to wrap where appropriate */
        .package-box-name-mobile .title-section .title,
        .package-box-name-mobile .title-section .title a,
        .package-page-layout-section-one .column-right .title-section .title {
            white-space: normal !important;
            word-break: break-word !important;
            overflow-wrap: anywhere !important;
        }
        /* Specific override for facultative block titles (template pages) */
        .package-page-facultative-section .title-section .title,
        .package-page-facultative-section .title-section .title a {
            white-space: normal !important;
            word-break: break-word !important;
            overflow-wrap: anywhere !important;
            min-width: 0 !important;
        }
    </style>
    <div id="cookie-banner" class="cookie-banner hidden" role="dialog" aria-live="polite" aria-label="Zgody na pliki cookie i prywatność">
        <div class="cookie-inner">
            <div class="cookie-text">
                Używamy plików cookie do poprawy działania serwisu oraz – za Twoją zgodą – do analityki i personalizacji/reklam Google.
                Szczegóły znajdziesz w <a href="{{ route('documents.global') }}" target="_blank" rel="noopener">polityce prywatności i cookies</a>.
                Możesz zaakceptować wszystkie, odrzucić niekonieczne lub wybrać ustawienia.
                <div id="cookie-settings" class="cookie-settings" aria-hidden="true">
                    <div class="cookie-setting"><input type="checkbox" id="consent_analytics"> <label for="consent_analytics">Analityczne (Analytics)</label></div>
                    <div class="cookie-setting"><input type="checkbox" id="consent_ads"> <label for="consent_ads">Reklamowe (Ad storage)</label></div>
                    <div class="cookie-setting"><input type="checkbox" id="consent_ad_user_data"> <label for="consent_ad_user_data">Wysyłanie danych do Google (Ad user data)</label></div>
                    <div class="cookie-setting"><input type="checkbox" id="consent_ad_personalization"> <label for="consent_ad_personalization">Personalizacja reklam</label></div>
                </div>
            </div>
            <div class="cookie-actions">
                <button id="cookie-reject" class="cookie-btn" type="button">Odrzuć</button>
                <button id="cookie-settings-toggle" class="cookie-btn" type="button" aria-expanded="false" aria-controls="cookie-settings">Ustawienia</button>
                <button id="cookie-accept" class="cookie-btn primary" type="button">Akceptuj</button>
            </div>
        </div>
    </div>
    <button id="cookie-manage" class="cookie-manage hidden" type="button" aria-label="Zarządzaj zgodami">Zarządzaj zgodami</button>
    <script>
        (function(){
            function setCookie(name, value, days){
                var expires = ""; if(days){ var d = new Date(); d.setTime(d.getTime() + (days*24*60*60*1000)); expires = "; expires=" + d.toUTCString(); }
                var secure = (location.protocol === 'https:') ? '; Secure' : '';
                document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=/; SameSite=Lax" + secure;
            }
            function getCookie(name){ var nameEQ = name + "="; var ca = document.cookie.split(';'); for(var i=0;i<ca.length;i++){ var c = ca[i]; while(c.charAt(0)==' ') c = c.substring(1,c.length); if(c.indexOf(nameEQ) == 0) return decodeURIComponent(c.substring(nameEQ.length,c.length)); } return null; }
            function applyConsent(cons){
                var m = {
                    'ad_storage': cons.ads?'granted':'denied',
                    'analytics_storage': cons.analytics?'granted':'denied',
                    'ad_user_data': cons.ad_user_data?'granted':'denied',
                    'ad_personalization': cons.ad_personalization?'granted':'denied'
                };
                // gtag route
                if(typeof window.gtag === 'function'){
                    try{ window.gtag('consent','update', m); }catch(e){}
                }
                // GTM route
                if(Array.isArray(window.dataLayer)){
                    try{ window.dataLayer.push(Object.assign({event:'consent_update'}, m)); }catch(e){}
                }
            }
            function defaultDeny(){
                var m = { 'ad_storage':'denied','analytics_storage':'denied','ad_user_data':'denied','ad_personalization':'denied','wait_for_update':500 };
                if(typeof window.gtag === 'function'){
                    try{ window.gtag('consent','default', m); }catch(e){}
                }
                if(Array.isArray(window.dataLayer)){
                    try{ window.dataLayer.push(Object.assign({event:'consent_default'}, m)); }catch(e){}
                }
            }
            function showBanner(show){ var el = document.getElementById('cookie-banner'); if(!el) return; el.classList.toggle('hidden', !show); var manage = document.getElementById('cookie-manage'); if(manage) manage.classList.toggle('hidden', show); }
            function openSettings(){ var box = document.getElementById('cookie-settings'); var btn = document.getElementById('cookie-settings-toggle'); if(!box||!btn) return; var exp = btn.getAttribute('aria-expanded') === 'true'; btn.setAttribute('aria-expanded', (!exp).toString()); box.style.display = exp? 'none':'block'; box.setAttribute('aria-hidden', exp? 'true':'false'); }

            // Init: set default denied before any tags fire
            defaultDeny();

            var saved = getCookie('cookie_consent_v2');
            if(saved){
                try{ var cons = JSON.parse(saved); applyConsent(cons); showBanner(false);}catch(e){ showBanner(true); }
            } else {
                showBanner(true);
            }

            document.getElementById('cookie-accept').addEventListener('click', function(){
                var cons = {analytics:true, ads:true, ad_user_data:true, ad_personalization:true, ts: Date.now()};
                setCookie('cookie_consent_v2', JSON.stringify(cons), 180);
                applyConsent(cons); showBanner(false);
            });
            document.getElementById('cookie-reject').addEventListener('click', function(){
                var cons = {analytics:false, ads:false, ad_user_data:false, ad_personalization:false, ts: Date.now()};
                setCookie('cookie_consent_v2', JSON.stringify(cons), 180);
                applyConsent(cons); showBanner(false);
            });
            document.getElementById('cookie-settings-toggle').addEventListener('click', openSettings);
            document.getElementById('cookie-manage').addEventListener('click', function(){ showBanner(true); });

            // Individual settings save on Accept when settings open
                // Ensure #cookie-manage is a direct child of body and enforce styles robustly
                (function(){
                    function enforceCookieManage(){
                        try{
                            var btn = document.getElementById('cookie-manage');
                            if(!btn) return;
                            if(btn.parentNode !== document.body) document.body.appendChild(btn);
                            // apply inline styles with high priority
                            btn.style.setProperty('position', 'fixed', 'important');
                            btn.style.setProperty('right', '12px', 'important');
                            btn.style.setProperty('left', 'auto', 'important');
                            btn.style.setProperty('bottom', '72px', 'important');
                            btn.style.setProperty('z-index', '20000', 'important');
                            btn.style.setProperty('max-width', 'calc(100vw - 24px)', 'important');
                            btn.style.setProperty('box-sizing', 'border-box', 'important');
                            btn.style.setProperty('transform', 'none', 'important');
                            btn.style.setProperty('overflow', 'visible', 'important');
                        }catch(e){/* ignore */}
                    }

                    // debounce helper
                    var resizeTimer = null;
                    function debouncedEnforce(){
                        clearTimeout(resizeTimer);
                        resizeTimer = setTimeout(enforceCookieManage, 120);
                    }

                    document.addEventListener('DOMContentLoaded', enforceCookieManage);
                    window.addEventListener('load', enforceCookieManage);
                    window.addEventListener('resize', debouncedEnforce);
                    // also run once after a short delay to catch late scripts
                    setTimeout(enforceCookieManage, 500);
                })();
            var acceptBtn = document.getElementById('cookie-accept');
            acceptBtn.addEventListener('click', function(){
                var box = document.getElementById('cookie-settings');
                if(box && box.style.display === 'block'){
                    var cons = {
                        analytics: !!document.getElementById('consent_analytics').checked,
                        ads: !!document.getElementById('consent_ads').checked,
                        ad_user_data: !!document.getElementById('consent_ad_user_data').checked,
                        ad_personalization: !!document.getElementById('consent_ad_personalization').checked,
                        ts: Date.now()
                    };
                    setCookie('cookie_consent_v2', JSON.stringify(cons), 180);
                    applyConsent(cons);
                    showBanner(false);
                }
            });
        })();
    </script>

</body>
</html>
