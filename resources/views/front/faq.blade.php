@extends('front.layout.master')

@section('main_content')
<div class="faq-area py-100">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <div class="section-title text-center mb-45">
                    <h2>Najczęstsze Pytania (FAQ)</h2>
                    <p>Tutaj znajdziesz odpowiedzi na najpopularniejsze pytania dotyczące naszych wycieczek szkolnych</p>
                </div>

                <div class="faq-accordion">
                    <!-- Pytanie 1 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            <span class="faq-question">Jakie wycieczki oferuje Biuro Podróży RAFA?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq1" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Organizujemy wycieczki dla grup zorganizowanych przede wszystkim dla szkół, firm i instytucji. W naszej ofercie znajdują się wycieczki jednodniowe i wielodniowe, zarówno krajowe, jak zagraniczne. Realizujemy wyjazdy autokarowe, lotnicze, promowe oraz przejazdem kolej.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 2 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            <span class="faq-question">Czy mogę zaplanować wycieczkę "szytą na miarę"?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq2" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Tak! Przygotowujemy programy indywidualne dopasowane do wieku uczestników, celu wyjazdu (edukacja, integracja, przygoda), budżetu i oczekiwań zamawiającego.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 3 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            <span class="faq-question">Czy organizujecie wycieczki zagraniczne?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq3" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Tak, organizujemy wycieczki zagraniczne do wielu krajów europejskich i pozaeuropejskich. Zapraszamy do zapoznania się z naszą ofertą lub skontaktowania się z nami w celu zaplanowania wycieczki zagranicznej.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 4 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            <span class="faq-question">Czy organizujecie wycieczki lotnicze?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq4" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Tak, w naszej ofercie znajdują się wycieczki lotnicze. Organizujemy wyloty z wybranych portów lotniczych.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 5 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq5">
                            <span class="faq-question">Jak dokonać rezerwacji wycieczki?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq5" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Rezerwację można dokonać poprzez naszą stronę internetową, telefonicznie lub mailowo. Po wybraniu interesującej nas wycieczki należy wypełnić formularz rezerwacji i przesłać go do nas. Nasz zespół skontaktuje się z Wami w celu potwierdzenia rezerwacji.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 6 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq6">
                            <span class="faq-question">Czy trzeba wpłacić zaliczkę?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq6" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Tak, aby zarezerwować wycieczkę, konieczne jest wpłacenie zaliczki w wysokości 30% ceny wycieczki.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 7 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq7">
                            <span class="faq-question">Kiedy należy opłacić całość wycieczki?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq7" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Pozostała część wycieczki (70%) należy opłacić nie później niż 7 dni przed terminem wyjazdu.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 8 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq8">
                            <span class="faq-question">Jak mogę zapłacić?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq8" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Akceptujemy płatności przelewem bankowym na podane konto, kartą kredytową, instrumentem BLIK oraz wpłatą gotówkową w naszym biurze.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 9 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq9">
                            <span class="faq-question">Czy mogę zapłacić online?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq9" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Tak, oferujemy płatności online kartą kredytową i przelewem bankowym.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 10 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq10">
                            <span class="faq-question">Jak otrzymać fakturę za wycieczkę?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq10" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>
                                    Aby otrzymać fakturę za udział w imprezie turystycznej (procedura marży dla biur podróży),
                                    wypełnij <a href="{{ route('invoice-request') }}">wniosek o fakturę</a> na stronie
                                    lub złóż go z poziomu portalu klienta przy swojej imprezie.
                                    Potrzebny będzie kod imprezy z umowy / oferty. Faktury wysyłamy e-mailem po zakończeniu wyjazdu.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 11 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq11">
                            <span class="faq-question">Gdzie mogę znaleźć dokumenty takie jak warunki uczestnictwa lub ubezpieczenie?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq11" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Wszystkie dokumenty dostępne są w sekcji <a href="{{ route('documents.global') }}">Dokumenty</a> na naszej stronie internetowej. Możesz tam pobrać polisy ubezpieczeniowe, warunki uczestnictwa i inne niezbędne dokumenty.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 12 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq12">
                            <span class="faq-question">Czy uczestnicy wycieczek szkolnych są ubezpieczeni?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq12" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Tak, wszyscy uczestnicy wycieczek szkolnych są ubezpieczeni. Ubezpieczenie obejmuje opiekę medyczną, koszty rezygnacji i inne usługi dodatkowe.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 13 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq13">
                            <span class="faq-question">Czy ubezpieczenie NNW RP jest konieczne?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq13" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Ubezpieczenie NNW nie jest obowiązkowe, ale rekomendujemy je dla dodatkowej ochrony.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 14 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq14">
                            <span class="faq-question">Czy ubezpieczenie KL jest konieczne?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq14" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Ubezpieczenie KL (kosztów leczenia) nie jest obowiązkowe, ale jeśli planujesz wycieczkę zagraniczną, zdecydowanie zalecamy takie ubezpieczenie.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 15 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq15">
                            <span class="faq-question">Co jeśli chcę zrezygnować z wycieczki?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq15" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Możliwość rezygnacji zależna jest od warunków uczestnictwa. Szczegóły dotyczące rezygnacji, wymaganego okresu wypowiedzenia i zwrotu pieniędzy znajdziesz w dokumencie warunków uczestnictwa dostępnym na naszej stronie.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 16 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq16">
                            <span class="faq-question">Czy wymagane są dokumenty to tożsamości?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq16" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Dla wycieczek krajowych mogą wystarczyć legitymacje szkolne. Dla wycieczek zagranicznych wymagane są paszporty lub dowody osobiste.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 17 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq17">
                            <span class="faq-question">Czy można sprawdzić autokar przed wyjazdem?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq17" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Tak, możesz zlecić nam przeprowadzenie oględzin autokaru. Prosimy o wcześniejsze powiadomienie, aby umówić się na konkretny termin.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 18 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq18">
                            <span class="faq-question">Czy mogę zarezerwować wycieczkę z dużym wyprzedzeniem?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq18" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Tak, rezerwacje możesz dokonywać nawet z rocznym wyprzedzeniem. Prosimy o kontakt, aby omówić szczegóły.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 19 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq19">
                            <span class="faq-question">Czy organizujecie wyjazdy firmowe i integracyjne?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq19" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Tak, organizujemy również wyjazdy firmowe, integracyjne i studyjne. Zapraszamy do kontaktu w celu zaplanowania idealnego wyjazdu dla Twojej grupy.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 20 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq20">
                            <span class="faq-question">Jak się z wami skontaktować?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq20" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Możesz nas kontaktować poprzez:</p>
                                <ul>
                                    <li>Telefon: +48 606 102 243</li>
                                    <li>Email: rafa@bprafa.pl</li>
                                    <li>Formularz kontaktowy na naszej <a href="{{ route('contact') }}">stronie kontaktu</a></li>
                                    <li>Osobiście w naszym biurze</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pytania i odpowiedzi - druga połowa -->
        <div class="row mt-50">
            <div class="col-lg-8 offset-lg-2">
                <div class="section-title text-center mb-45">
                    <h3>Dodatkowe Informacje</h3>
                </div>

                <div class="faq-accordion">
                    <!-- Pytanie 21 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq21">
                            <span class="faq-question">Czy pilot jest zapewniony?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq21" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Tak, każdą wycieczkę oprowadza doświadczony pilot turystyczny, który zapewni oprawę merytoryczną i historyczną odwiedzanym miejscom.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 22 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq22">
                            <span class="faq-question">Czy przewodnik jest zapewniony?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq22" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Tak, w wybranych miejscach dostarczamy lokalnych przewodników specjalizujących się w danym regionie.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 23 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq23">
                            <span class="faq-question">Jakim autokarem odbywa się przejazd?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq23" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Wycieczki odbywają się nowoczesnym autokarem, wyposażonym w klimatyzację, toaletę, bose, telewizor i WiFi (w wybranych autosach).</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 24 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq24">
                            <span class="faq-question">Czy kierowcy mają wymagane przerwy?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq24" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Tak, wszyscy nasze kierowcy przestrzegają przepisów o czasach pracy i wymaganymi przerwami zgodnie z obowiązującym prawem.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 25 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq25">
                            <span class="faq-question">Jakiego standardu są hotele i ośrodki noclegowe?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq25" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Współpracujemy z hotelami 2-4 gwiazdkowymi wbrew kierunku wycieczki. Wszystkie obiekty spełniają wysokie standardy czystości i bezpieczeństwa.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 26 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq26">
                            <span class="faq-question">Czy pokoje są z łazienkami?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq26" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Zdecydowana większość pokoi posiada prywatne łazienki. W przypadku wycieczek budżetowych pokoje mogą się znajdować na piętrze (łazienka wspólna).</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 27 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq27">
                            <span class="faq-question">Czy można zamówić dietę (wegetariańską, bezglutenową itp.)?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq27" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Tak, informacje o specjalnych dietach należy podać podczas rezerwacji. Będziemy starać się dostosować posiłki do indywidualnych potrzeb uczestników.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 28 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq28">
                            <span class="faq-question">Co dokładnie zawiera cena wycieczki?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq28" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Cena wycieczki zawiera zwykle transport autokarem, pobyty hotelowe (noclegi i wyżywienie), opłaty za wejścia do atrakcji turystycznych, usługi pilota i przewodnika. Szczegółowy opis zawartości ceny znajduje się w opisie każdej wycieczki.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 29 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq29">
                            <span class="faq-question">Czy ceny na stronie internetowej są aktualne?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq29" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Tak, ceny na stronie internetowej są aktualizowane na bieżąco. W razie wątpliwości prosimy o kontakt z naszym biurem w celu potwierdzenia ceny.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pytanie 30 -->
                    <div class="faq-item">
                        <button class="faq-button" data-bs-toggle="collapse" data-bs-target="#faq30">
                            <span class="faq-question">Czy są jakieś ukryte koszty?</span>
                            <span class="faq-icon"><i class="fas fa-plus"></i></span>
                        </button>
                        <div id="faq30" class="collapse" data-bs-parent=".faq-accordion">
                            <div class="faq-answer">
                                <p>Nie, wszystkie koszty są zawarte w cenie wycieczki lub wyraźnie wskazane jako opcjonalne. Nie ma żadnych ukrytych opłat.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.faq-area {
    padding: 60px 0;
    background: #f8f9fa;
}

.faq-item {
    margin-bottom: 15px;
    background: white;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    overflow: hidden;
}

.faq-button {
    width: 100%;
    padding: 20px;
    background: white;
    border: none;
    text-align: left;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
    font-size: 16px;
    font-weight: 500;
    color: #333;
}

.faq-button:hover {
    background: #f0f0f0;
}

.faq-button.collapsed {
    color: #666;
}

.faq-question {
    flex: 1;
}

.faq-icon {
    margin-left: 15px;
    color: #0066cc;
    font-size: 18px;
    transition: transform 0.3s ease;
}

.faq-button[aria-expanded="true"] .faq-icon {
    transform: rotate(45deg);
}

.faq-answer {
    padding: 0 20px 20px 20px;
    background: white;
    color: #666;
    line-height: 1.6;
}

.faq-answer p {
    margin-bottom: 10px;
}

.faq-answer ul {
    margin-left: 20px;
    margin-bottom: 10px;
}

.faq-answer li {
    margin-bottom: 8px;
}

.faq-answer a {
    color: #0066cc;
    text-decoration: none;
}

.faq-answer a:hover {
    text-decoration: underline;
}

.section-title {
    margin-bottom: 40px;
}

.section-title h2 {
    font-size: 32px;
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
}

.section-title h3 {
    font-size: 24px;
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
}

.section-title p {
    color: #666;
    font-size: 16px;
}
</style>
@endsection
