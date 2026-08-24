<?php

namespace Database\Seeders;

use App\Models\FaqEntry;
use App\Models\SeoSetting;
use Illuminate\Database\Seeder;

class FaqEntrySeeder extends Seeder
{
    public function run(): void
    {
        if (FaqEntry::query()->exists()) {
            return;
        }

        SeoSetting::setValue('organization', SeoSetting::organizationDefaults());

        $entries = [
            ['Jakie wycieczki oferuje Biuro Podróży RAFA?', 'Organizujemy wycieczki dla grup zorganizowanych przede wszystkim dla szkół, firm i instytucji. W naszej ofercie znajdują się wycieczki jednodniowe i wielodniowe, krajowe i zagraniczne — autokarem, samolotem, promem lub pociągiem.', FaqEntry::CATEGORY_SCHOOL, FaqEntry::SCOPE_FAQ_PAGE, 10],
            ['Czy mogę zaplanować wycieczkę szytą na miarę?', 'Tak! Przygotowujemy programy indywidualne dopasowane do wieku uczestników, celu wyjazdu (edukacja, integracja, przygoda), budżetu i oczekiwań zamawiającego.', FaqEntry::CATEGORY_SCHOOL, FaqEntry::SCOPE_FAQ_PAGE, 20],
            ['Czy organizujecie wycieczki zagraniczne?', 'Tak, organizujemy wycieczki zagraniczne do wielu krajów europejskich. Zapraszamy do zapoznania się z ofertą lub kontaktu w celu zaplanowania wyjazdu.', FaqEntry::CATEGORY_SCHOOL, FaqEntry::SCOPE_FAQ_PAGE, 30],
            ['Czy organizujecie wyjazdy firmowe i integracyjne?', 'Tak, organizujemy wyjazdy firmowe, integracyjne i studyjne. Przygotowujemy program dopasowany do celu wyjazdu zespołu.', FaqEntry::CATEGORY_CORPORATE, FaqEntry::SCOPE_FAQ_PAGE, 40],
            ['Jak dokonać rezerwacji wycieczki?', 'Rezerwację można dokonać przez stronę internetową, telefonicznie lub mailowo. Po wybraniu wycieczki wypełnij formularz zapytania — skontaktujemy się w celu potwierdzenia.', FaqEntry::CATEGORY_BOOKING, FaqEntry::SCOPE_FAQ_PAGE, 50],
            ['Czy trzeba wpłacić zaliczkę?', 'Tak, aby zarezerwować wycieczkę, konieczne jest wpłacenie zaliczki w wysokości 30% ceny wycieczki.', FaqEntry::CATEGORY_PAYMENTS, FaqEntry::SCOPE_FAQ_PAGE, 60],
            ['Kiedy należy opłacić całość wycieczki?', 'Pozostałą część wycieczki (70%) należy opłacić nie później niż 7 dni przed terminem wyjazdu.', FaqEntry::CATEGORY_PAYMENTS, FaqEntry::SCOPE_FAQ_PAGE, 70],
            ['Jak mogę zapłacić?', 'Akceptujemy płatności przelewem bankowym, kartą kredytową, BLIK oraz wpłatą gotówkową w biurze.', FaqEntry::CATEGORY_PAYMENTS, FaqEntry::SCOPE_FAQ_PAGE, 80],
            ['Czy uczestnicy wycieczek szkolnych są ubezpieczeni?', 'Tak, wszyscy uczestnicy wycieczek szkolnych są ubezpieczeni. Ubezpieczenie obejmuje opiekę medyczną i koszty rezygnacji.', FaqEntry::CATEGORY_INSURANCE, FaqEntry::SCOPE_FAQ_PAGE, 90],
            ['Czy pilot jest zapewniony?', 'Tak, każdą wycieczkę oprowadza doświadczony pilot turystyczny zapewniający oprawę merytoryczną programu.', FaqEntry::CATEGORY_SCHOOL, FaqEntry::SCOPE_FAQ_PAGE, 100],
            ['Jakim autokarem odbywa się przejazd?', 'Wycieczki odbywają się nowoczesnymi autokarami z klimatyzacją. Wyposażenie zależy od przewoźnika i trasy.', FaqEntry::CATEGORY_TRANSPORT, FaqEntry::SCOPE_FAQ_PAGE, 110],
            ['Co dokładnie zawiera cena wycieczki?', 'Cena zwykle obejmuje transport, noclegi, wyżywienie, opłaty za atrakcje, usługi pilota i przewodnika. Szczegóły są w opisie każdej oferty.', FaqEntry::CATEGORY_SCHOOL, FaqEntry::SCOPE_FAQ_PAGE, 120],
            ['Jak się z wami skontaktować?', 'Telefon: +48 606 102 243, e-mail: rafa@bprafa.pl, formularz kontaktowy na stronie lub wizyta w biurze w Warszawie.', FaqEntry::CATEGORY_GENERAL, FaqEntry::SCOPE_FAQ_PAGE, 130],

            ['Czy Biuro Podróży RAFA organizuje tylko wycieczki szkolne?', 'Specjalizujemy się w wycieczkach szkolnych, ale obsługujemy też wyjazdy firmowe, integracyjne i grupy zorganizowane.', FaqEntry::CATEGORY_GENERAL, FaqEntry::SCOPE_ABOUT, 10],
            ['Skąd organizujecie wyjazdy?', 'Obsługujemy grupy z całej Polski. Miejsce zbiórki dopasowujemy do lokalizacji szkoły lub firmy.', FaqEntry::CATEGORY_GENERAL, FaqEntry::SCOPE_ABOUT, 20],
            ['Czy posiadacie licencję organizatora turystyki?', 'Tak, jesteśmy wpisani do rejestru organizatorów turystyki i pośredników turystycznych (TFG).', FaqEntry::CATEGORY_GENERAL, FaqEntry::SCOPE_ABOUT, 30],

            ['Jak zorganizować wycieczkę szkolną krok po kroku?', 'Wybierz kierunek z oferty lub poproś o program indywidualny, wyślij zapytanie, podpisz umowę, wpłać zaliczkę i przygotuj grupę do wyjazdu.', FaqEntry::CATEGORY_SCHOOL, FaqEntry::SCOPE_HOME, 10],
            ['Ile wcześniej trzeba zarezerwować wycieczkę szkolną?', 'Rekomendujemy rezerwację z kilkumiesięcznym wyprzedzeniem, choć przyjmujemy zapytania także na krótszy termin — zależnie od dostępności.', FaqEntry::CATEGORY_BOOKING, FaqEntry::SCOPE_HOME, 20],
            ['Czy można zamówić wycieczkę poza katalogiem?', 'Tak — przygotujemy program indywidualny dopasowany do wieku uczniów, budżetu i celu wyjazdu.', FaqEntry::CATEGORY_SCHOOL, FaqEntry::SCOPE_HOME, 30],
            ['Czy faktura VAT jest dostępna dla wyjazdu firmowego?', 'Tak, wystawiamy faktury dla firm i instytucji zgodnie z warunkami umowy.', FaqEntry::CATEGORY_CORPORATE, FaqEntry::SCOPE_HOME, 40],

            ['Ile kosztuje wycieczka szkolna?', 'Cena zależy od kierunku, długości wyjazdu, wielkości grupy i środka transportu. Aktualne ceny są widoczne przy każdej ofercie na stronie.', FaqEntry::CATEGORY_SCHOOL, FaqEntry::SCOPE_PACKAGE, 10],
            ['Co jest wliczone w cenę tej wycieczki?', 'Zakres usług opisujemy w szczegółach oferty — zwykle obejmuje transport, noclegi, wyżywienie i program zwiedzania.', FaqEntry::CATEGORY_SCHOOL, FaqEntry::SCOPE_PACKAGE, 20],
            ['Czy mogę zmienić program wycieczki?', 'Tak, oferujemy modyfikacje programu po uzgodnieniu z koordynatorem — przed podpisaniem umowy.', FaqEntry::CATEGORY_SCHOOL, FaqEntry::SCOPE_PACKAGE, 30],
        ];

        foreach ($entries as [$question, $answer, $category, $scope, $sortOrder]) {
            FaqEntry::query()->create([
                'question' => $question,
                'answer' => '<p>'.$answer.'</p>',
                'category' => $category,
                'scope' => $scope,
                'sort_order' => $sortOrder,
                'is_published' => true,
                'include_in_schema' => true,
            ]);
        }
    }
}
