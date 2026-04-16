<?php

namespace Database\Seeders;

use App\Models\ContractTemplate;
use Illuminate\Database\Seeder;

class AgreementContractTemplateSeeder extends Seeder
{
    public function run(): void
    {
        ContractTemplate::updateOrCreate(
            ['name' => 'Umowa indywidualna online (RAFA)'],
            ['content' => trim(<<<'TEMPLATE'
    Potwierdzenie zawarcia umowy o organizację imprezy turystycznej
nr [NUMER_UMOWY]

    Umowa zawarta dnia [DATA_UMOWY] pomiędzy:
    Zamawiającym: [ZAMAWIAJACY_IMIE_NAZWISKO]
    Instytucja / szkoła (jeśli dotyczy): [ZAMAWIAJACY_INSTYTUCJA]
Adres: [ZAMAWIAJACY_ADRES]
    Telefon: [ZAMAWIAJACY_TELEFON]
    Email: [ZAMAWIAJACY_EMAIL]
    zwanym dalej Zamawiającym,

    a Organizatorem:
[ORGANIZATOR_NAZWA]
[ORGANIZATOR_ADRES_1]
[ORGANIZATOR_ADRES_2]
Telefon: [ORGANIZATOR_TELEFON]
Email: [ORGANIZATOR_EMAIL]
    zwanym dalej Organizatorem.

INFORMACJE O IMPREZIE TURYSTYCZNEJ
    Imię i nazwisko uczestnika: [UCZESTNIK]
Data urodzenia uczestnika: [DATA_URODZENIA]
    Adres email uczestnika: [UCZESTNIK_EMAIL]
    Telefon uczestnika: [UCZESTNIK_TELEFON]
    Rodzaj imprezy turystycznej: [TYP_UMOWY]
    Destynacja / nazwa imprezy: [NAZWA_IMPREZY]
    Termin wycieczki: [DATA_START] - [DATA_KONIEC]
    Środek transportu: Autokar turystyczny
    Wyjazd: [DATA_WYJAZDU], [MIEJSCE_WYJAZDU], godz. [GODZINA_WYJAZDU]
    Powrót: [DATA_POWROTU], [MIEJSCE_POWROTU], godz. [GODZINA_POWROTU]
    Obiekt noclegowy: zgodnie z programem
    Wyżywienie: zgodnie z programem
    Ubezpieczenie: NNW w wersji Standard Plus do kwoty 30 000 zł/osoba
    Dodatkowe ubezpieczenie: [DODATKOWE_UBEZPIECZENIE]
    Dodatkowe informacje: Opiekę nad małoletnimi dziećmi sprawują opiekunowie / nauczyciele wskazani przez Zamawiającego.

    PODSTAWIENIE AUTOKARU I MIEJSCE ZBIÓRKI
    Miejsce podstawienia autokaru: [MIEJSCE_WYJAZDU]
    Godzina podstawienia autokaru: [GODZINA_WYJAZDU]

    Uwaga! Autokar zostanie podstawiony w miejscu wskazanym w umowie. Zamawiający lub Podróżny
    mają prawo do poproszenia odpowiednich służb o przeprowadzenie kontroli stanu technicznego
    autokaru oraz trzeźwości kierowcy. Kontrola taka odbywa się w miejscu podstawienia autokaru
    na 30 minut przed planowaną godziną wyjazdu. Wszelkie formalności związane z wezwaniem służb
    uprawnionych do kontroli pozostają po stronie Zamawiającego/Podróżnego zlecającego kontrolę.

    CENA IMPREZY I HARMONOGRAM WPŁAT
Cena brutto: [KWOTA] [WALUTA]
    Cena jednostkowa: [CENA_JEDNOSTKOWA] [WALUTA] / osoba
    Sposób płatności: przelew / płatność online
    W opisie przelewu należy wpisać: imię i nazwisko uczestnika, rezerwacja nr [REFERENCJA_REZERWACJI]

    Brak zapłaty w wyznaczonym terminie jest jednoznaczny z rezygnacją przez Zamawiającego z imprezy turystycznej.

    ZAŁĄCZNIKI DO UMOWY
    1. Warunki Uczestnictwa w imprezach organizowanych przez Biuro Podróży RAFA - załącznik nr 1
    2. Program zwiedzania (oferta) - załącznik nr 2
    3. Standardowy formularz informacyjny - załącznik nr 3
    4. Warunki ubezpieczenia NNW przy wyjazdach krajowych - załącznik nr 4

    Link do umowy i płatności:
[LINK_UMOWY]

    Dokument został wygenerowany elektronicznie. Nie wymaga pieczęci ani podpisu.
    Załączniki i sama umowa powinny być dostarczane w plikach PDF.
TEMPLATE)],
        );

        ContractTemplate::updateOrCreate(
            ['name' => 'Umowa grupowa online (RAFA)'],
            ['content' => trim(<<<'TEMPLATE'
    Potwierdzenie zawarcia umowy o organizację imprezy turystycznej
nr [NUMER_UMOWY]

    Umowa zawarta dnia [DATA_UMOWY] pomiędzy:
    zamawiającym:
    [ZAMAWIAJACY_IMIE_NAZWISKO]
    Instytucja / szkoła: [ZAMAWIAJACY_INSTYTUCJA]
    Kontakt: [ZAMAWIAJACY_TELEFON], [ZAMAWIAJACY_EMAIL]

    a organizatorem:
[ORGANIZATOR_NAZWA]
[ORGANIZATOR_ADRES_1]
[ORGANIZATOR_ADRES_2]
Telefon: [ORGANIZATOR_TELEFON]
Email: [ORGANIZATOR_EMAIL]

INFORMACJE O IMPREZIE TURYSTYCZNEJ
    Rodzaj imprezy turystycznej: [TYP_UMOWY]
    Nazwa imprezy / destynacja: [NAZWA_IMPREZY]
    Termin wycieczki: [DATA_START] - [DATA_KONIEC]
    Środek transportu: Autokar turystyczny
    Ilość uczestników (łącznie z opiekunami): [LICZBA_OSOB]
    Ilość opiekunów: zgodnie z ustaleniami stron
Miejsce wyjazdu: [MIEJSCE_WYJAZDU]
    Wyjazd: [DATA_WYJAZDU] godz. [GODZINA_WYJAZDU] - [MIEJSCE_WYJAZDU]
Miejsce powrotu: [MIEJSCE_POWROTU]
    Powrót: [DATA_POWROTU] godz. [GODZINA_POWROTU] - [MIEJSCE_POWROTU]
    Obiekt noclegowy: zgodnie z programem
    Wyżywienie: zgodnie z programem
    Ubezpieczenie: NNW Signal Iduna do kwoty 30 000 zł/os. w wersji Standard Plus
    Dodatkowe informacje: Opiekę nad małoletnimi dziećmi sprawują nauczyciele / opiekunowie wskazani przez Zamawiającego.

    PODSTAWIENIE I MIEJSCE ZBIÓRKI
    Miejsce podstawienia autokaru: [MIEJSCE_WYJAZDU]
    Godzina podstawienia: [DATA_WYJAZDU] godz. [GODZINA_WYJAZDU]

    Uwaga! Autokar zostanie podstawiony w miejscu wskazanym w umowie. Zamawiający lub Podróżny mają prawo do poproszenia
    odpowiednich służb o przeprowadzenie kontroli stanu technicznego autokaru oraz trzeźwości kierowcy. Kontrola taka odbywa się
    w miejscu podstawienia autokaru na 30 minut przed planowaną godziną wyjazdu. Wszelkie formalności związane z wezwaniem służb
    uprawnionych do kontroli pozostają po stronie Zamawiającego/Podróżnego zlecającego kontrolę.

    CENA IMPREZY I HARMONOGRAM WPŁAT
Cena brutto: [KWOTA] [WALUTA]
    Cena jednostkowa: [CENA_JEDNOSTKOWA] [WALUTA] / osoba
    Słownie: zgodnie z wartością liczbową ceny brutto
    Sposób płatności: przelew
    Dodatkowe ubezpieczenie: [DODATKOWE_UBEZPIECZENIE]
    W opisie przelewu należy podać: [NAZWA_IMPREZY], rezerwacja nr [REFERENCJA_REZERWACJI]

    Wpłata zaliczki na konto Biura Podróży RAFA jest jednoznaczna z zawarciem umowy oraz akceptacją warunków
    uczestnictwa w imprezach organizowanych przez Biuro Podróży RAFA.

    Brak zapłaty w wyznaczonym terminie jest jednoznaczny z rezygnacją przez Zamawiającego z organizacji imprezy turystycznej.

    ZAŁĄCZNIKI DO UMOWY
    1. Warunki uczestnictwa w imprezach organizowanych przez Biuro Podróży RAFA - załącznik nr 1
    2. Program zwiedzania (oferta) - załącznik nr 2
    3. Standardowy formularz informacyjny - załącznik nr 3
    4. Warunki ubezpieczenia NNW przy wyjazdach krajowych i KL przy wyjazdach zagranicznych - załącznik nr 4

    Link do umowy i płatności:
[LINK_UMOWY]

    Dokument został wygenerowany elektronicznie. Nie wymaga pieczęci ani podpisu.
TEMPLATE)],
        );
    }
}
