<!-- Style przeniesione do public/dist-front/css/style.css -->
@extends('front.layout.master')

@section('main_content')
    <div class="page-top">
        <div class="container">
            <div class="breadcrumb-container">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{route('home')}}">Start</a></li>
                    <li class="breadcrumb-item active">Ubezpieczenia</li>
                </ol>
            </div>
        </div>
    </div>
    <div class="container">
    <div class="insurance pt_50 pb_70">
        <div class="top-section">
        <div class="box-for-picture pb_10">
            <div class="insurance-picture" style="background-image:url({{ asset('uploads/insurance_photo.jpg') }});">
                <div class="insurance-picture-space">
                    <div class="insurance-text-ad">
            Planujesz wycieczkę szkolną?<br>
            <div class="hide-mobile">Dobierz swoje ulubione ubezpieczenie.</div>
                    </div>
                    <a href="https://sklep.signal-iduna.pl/?portal_code=www.bprafa.pl&amp;ag_symbol=42505&amp;tax_number=7162508761" class="buy-button buy-button-mobile" style="display:inline-flex !important; z-index:3; position:relative;" target="_blank" rel="noopener" aria-label="Wykup ubezpieczenie">
                        Wykup ubezpieczenie
                    </a>
                </div>
            </div>
        </div>
        </div>
        <div class="price-cards pt_70">
            <div class="card">
                <div class="icon-ball">
                <div class="icon">NNW</div></div>
                <h4><b>NNW RP</b><br>Ubezpieczenie podczas wyjazdów krajowych</h4>
                <div class="included"><div class="incl">wliczone w cenę wycieczki</div></div>
                
                <ul class="price-cards-details">
                    <h6>Zakres ubezpieczenia:</h6>
                    <li><i class="fas fa-check"></i> Ubezpieczenie NNW do kwoty 30 000 zł/osoba</li>
                    <li><i class="fas fa-check"></i> Ubezpieczenie Assistance</li>
                </ul>
                <div class="buttons">
                <a class="check-insurance" href="#nnw">Zakres ubezpieczenia</a>
                <a class="pdf" href="{{ asset('storage/nnw_ow_rp.pdf') }}" target="_blank" rel="noopener">Ogólne warunki (PDF)</a>
                </div>
            </div>
            <div class="card">
                <div class="icon-ball"><div class="icon">KL</div></div>
                <h4><b>Bezpieczne Podróże</b><br>Ubezpieczenie podczas wyjazdów zagranicznych</h4>
                <div class="included" ><div class="incl">wliczone w cenę wycieczki</div></div>
                <ul class="price-cards-details">
                    <h6>Zakres ubezpieczenia:</h6>
                    <li><i class="fas fa-check"></i> Ubezpieczenie KL i Asistatanse do kwoty 70 000 euro/osoba</li>
                    <li><i class="fas fa-check"></i> Ubezpieczenie NNW do kwoty 35 000 zł/osoba</li>
                    <li><i class="fas fa-check"></i> Ubezpieczenie OC do kwoty 60 000 euro/osoba </li>
                    <li><i class="fas fa-check"></i> Ubezpieczenie bagażu podróżnego do kwoty 2 500 zł</li>
                </ul>
                <div class="buttons">
                <a class="check-insurance" href="#kl">Zakres ubezpieczenia</a>
                <a class="pdf" href="{{ asset('storage/kl_ow.pdf') }}" target="_blank" rel="noopener">Ogólne warunki (PDF)</a>
                </div>
            </div>
            <div class="card">
                <div class="icon-ball"><div class="icon">KR</div></div>
                <h4><b>Bezpieczne Rezerwacje</b><br>Ubezpieczenie kosztów rezygnacji</h4>
                <div class="buttons pre-button" style="margin-top:8px;">
                    <a class="check-insurance" href="https://sklep.signal-iduna.pl/?portal_code=www.bprafa.pl&amp;ag_symbol=42505&amp;tax_number=7162508761" target="_blank" rel="noopener" aria-label="Wykup ubezpieczenie">Wykup ubezpieczenie</a>
                </div>
                <div class="not-included">
                    <div class="notincl">opcja płatna dodatkowo 3,2% ceny wycieczki</div>
                </div>
                <ul class="price-cards-details">
                    <h6>Zakres ubezpieczenia:</h6>
                    <li><i class="fas fa-check"></i> Ubezpieczenie całej ceny rezerwacji
                    </li>
                    <li><i class="fas fa-check"></i> Zwrot 100% poniesionych kosztów w przypadku rezygnacji
                    </li>
                </ul>
                <div class="buttons">
                <a class="check-insurance" href="#kr">Zakres ubezpieczenia</a>
                <a class="pdf" href="{{ asset('storage/kr_ow.pdf') }}" target="_blank" rel="noopener">Ogólne warunki (PDF)</a>
                </div>
            </div>
        </div>

        <div class="insurance-table pt_40">
            <div class="table-box">
                <div class="insurance-name" id="nnw"><h4 style="text-align: left">Ubezpieczenie NNW RP</h4><br>
                </div>
                <div class="insurance-details">
                    <b><p>Ubezpieczenie Następstw Nieszczęśliwych Wypadków</b> oraz <b>Ubezpieczenie Assistance</b>.<br><br></p>
                    <ul class="insurance-list"><b>Zakres działania ubezpieczenia NNW RP:</b>
                        <li>wypłata świadczenia z tytułu uszczerbku na zdrowiu</li>
                        <li>wypłata świadczenia z tytułu śmierci</li>
                        <li>zwrot kosztów wizyty lekarza</li>
                        <li>transport medyczny na terenie Polski</li>
                        <li>refundacja kosztów wiztyty osoby bliskiej w przypadku hospitalizacji (na okres do 7 dni)</li>
                        <li>refundacja kosztów transportu zwłok ubezpieczonego</li></ul>
                        <br></div>
            </div>
            <div class="table-box">
                <div class="insurance-name" id="kl"><h4 style="text-align: left">Ubezpieczenie Bezpieczne Podróże</h4><br>
                </div>
                <div class="insurance-details">
                    <p><b>Bezpieczne Podróże</b> to kompleksowa ochrona ubezpieczeniowa dla osób wyjeżdżających za granicę w celach wypoczynkowych, turystycznych, edukacyjnych i biznesowych.
                    <br><br><ul class="insurance-list"><b>Ubezpieczenie KL zadziała bez dopłat w przypadku:</b>
                    <li>uprawiania sportów amatorskich,</li>
                    <li>zaostrzenia choroby przewlekłej</li>
                    <li>ataku terrorystycznego</li>
                    <li>w zakresie szkód po spożyciu alkoholu (z wyłączeniem OC i wypadku komunikacyjnego),</li>
                    <li>nagłego zachorowania spowodowanego Sars-Cov-1 lub Sars-Cov-2 z ich mutacjami</li></ul><p>
                    <br><b>Ubezpieczenie Assistance</b> obejmuje transport medyczny bez limitu kosztów i bez zmniejszenia sumy ubezpieczenia na koszty leczenia
                    </p></div>
            </div>
            <div class="table-box">
                <div class="insurance-name" id="kr"><h4 style="text-align: left">Ubezpieczenie Bezpieczne Rezerwacje</h4><br>
                </div>
                <div class="insurance-details">
                    <p><b>Bezpieczne rezerwacje</b> to sposób na ubezpieczenie kosztów rezygnacji. Ubezpieczamy <b>100% ceny</b> wycieczki.
                        <br><br></p>
                    <ul class="insurance-list"><b>Powody z których można zrezygnować z udziału w wycieczce, aby ubezpieczenie zadziałało:</b>
                        <li>nagłe zachorowanie Ubezpieczonego, Współuczestnika podróży lub Osób im bliskich skutkujące leczeniem ambulatoryjnym lub hospitalizacją (w tym COVID, choroby zakaźne),</li>
                        <li>śmierć Ubezpieczonego, Współuczestnika podróży lub Osób im bliskich (w tym w wyniku zaostrzenia choroby przewlekłej, COVID),</li>
                        <li>wyznaczenie terminu porodu na czas trwania wycieczki,</li>
                        <li>rozpoczęcie procesu pobierania krwiotwórczych komórek,</li>
                        <li>reakcja alergiczna na szczepienia, które były niezbędne do uczestnictwa w podróży,</li>
                        <li>szkoda w mieniu,</li>
                        <li>kradzież samochodu,</li>
                        <li>kradzież dokumentów niezbędnych w podroży,</li>
                        <li>oszustwo na rachunku bankowym lub karcie kredytowej (kradzież środków),</li>
                        <li>szkoda w mieniu pracodawcy,</li>
                        <li>nieszczęśliwy wypadek w pracy powodujacy wykonanie czynności prawnych w trakcie trwania podróży,</li>
                        <li>wyznaczenie daty rozpoczęcia pracy,</li>
                        <li>wypowiedzenie umowy o pracę,</li>
                        <li>unieruchomienie na 24 godziny przed podróżą pojadu Ubezpieczonego lub Współuczestnika podróży,</li>
                        <li>wezwanie do stawiennictwa w sądzie,</li>
                        <li>otrzymanie propozycji adopcji dziecka,</li>
                        <li>otrzymanie powołania do rozgrywek sportowych o randze międzynarodowej,</li>
                        <li>otrzymanie wezwania do służby wojskowej,</li>
                        <li>rozpoczęcie leczenia uzdrowiskowego,</li>
                        <li>wyznaczenie na czas trwania podroży egzaminu poprawkowego którego niezaliczenie spowoduje usunięcie ubezpieczonego z listy uczniów/studentów,</li>
                        <li>uczestnictwo w olimpiadzie międzyszkolnej organizowanej przez MEN,</li>
                        <li>wyznaczenie obrony pracy dyplomowej na uczelni wyższej,</li>
                        <li>wystąpienie aktu terroru,</li>
                        <li>nagłe zachorowanie (lub nieszczęśliwy wypadek) zwierzęcia którego właścicielem jest ubezpieczony,</li>
                        <li>odwołanie konferencji przez organizatora konferencji.</li></ul></div>
            </div>
        </div>
    </div>
    </div>
@endsection

<style>
    /* Smooth scroll for modern browsers */
    html {
        scroll-behavior: smooth;
    }

    /* Mobile / Tablet specific UI for insurance buy module - limited to this blade file */
    .mobile-insurance-module { display: none; }

    /* Show mobile module on small and medium screens (tablet + phone) */
    @media (max-width: 991px) {
        .mobile-insurance-module { display: block; margin: 12px 0; }
        .mobile-insurance-module .card {
            padding: 14px;
            border-radius: 10px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.08);
            background: #fff;
        }
        .mobile-insurance-module .icon-ball { width:48px; height:48px; }
        .mobile-insurance-module .icon { font-size:14px; }
        .mobile-insurance-module .buttons { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
        .mobile-insurance-module .buy-insurance {
            background: #007bff;
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 6px rgba(0,0,0,0.12);
        }
        .mobile-insurance-module .buy-insurance:active,
        .mobile-insurance-module .buy-insurance:focus { outline: none; transform: translateY(0.5px); }
    }
</style>

<script>
    // Fallback JS smooth scroll with offset for fixed headers
    document.addEventListener('DOMContentLoaded', function () {
        var headerOffset = 80; // adjust if your header height differs
        document.querySelectorAll('a.check-insurance').forEach(function (anchor) {
            anchor.addEventListener('click', function (e) {
                // only handle in-page anchors
                var href = anchor.getAttribute('href');
                if (!href || href.charAt(0) !== '#') return;
                var id = href.slice(1);
                var target = document.getElementById(id);
                if (!target) return;
                e.preventDefault();
                var elementPosition = target.getBoundingClientRect().top + window.pageYOffset;
                var offsetPosition = elementPosition - headerOffset;
                window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
                // Update URL hash without jumping
                history.replaceState(null, null, '#' + id);
            });
        });
    });
</script>
