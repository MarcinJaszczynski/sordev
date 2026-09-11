<?php

declare(strict_types=1);

namespace App\Services\Documents;

use NumberFormatter;

/**
 * Stała treść prawna i reguły formatowania umowy DOCX (wzór PDF RAFA).
 *
 * Kontroler PhpWord zostaje cienki — tu OWU, SFI, organizator, konto, słownie.
 */
final class WordAgreementContent
{
    public const ORGANIZER_NAME = 'Biuro Podróży RAFA - Rafał Latos';

    public const ORGANIZER_ADDRESS = 'ul. Marii Konopnickiej 6, 00-491 Warszawa';

    public const ORGANIZER_NIP = '716-250-87-61';

    public const ORGANIZER_REGON = '432298189';

    public const ORGANIZER_REGISTRY = 'Wpis do Rejestru Organizatorów Turystyki i Pośredników Turystycznych Województwa Mazowieckiego nr 1270';

    public const ORGANIZER_INSURANCE = 'Polisa OC Organizatora Turystyki nr M 520855 Signal Iduna Polska Towarzystwo Ubezpieczeń S.A.';

    public const BANK_NAME = 'Bank Millenium S.A.';

    public const BANK_ACCOUNT = '10 1160 2202 0000 0002 0065 6958';

    public const DEFAULT_TRANSPORT = 'Autokar turystyczny';

    public const DEFAULT_MEALS = 'zgodnie z programem';

    public const DEFAULT_ADDITIONAL_INFO = 'Opiekę nad małoletnimi dziećmi będą sprawowali nauczyciele szkolni';

    public const DEFAULT_INSURANCE_DOMESTIC = 'NNW Signal Iduna do kwoty 30 000 zł/osoba w wersji Standard na czas pobytu w PL';

    public const DEFAULT_INSURANCE_FOREIGN = 'NNW Signal Iduna do kwoty 30 000 zł/osoba w wersji Standard na czas pobytu w PL i KL do kwoty 300 000 euro/osoba na czas pobytu za granicą';

    public const DEFAULT_PRICE_INCLUDES = 'Zakwaterowanie i wyżywienie zgodnie z programem, przejazd autokarem, opiekę pilota, przewodników lokalnych, bilety wstępu na realizacje programu, ubezpieczenie NNW do kwoty 30 000 zł/osoba w wersji standard, podatek VAT, miejsca gratis dla opiekunów';

    public const ATTACHMENTS = [
        '1. Warunki uczestnictwa w imprezach organizowanych przez Biuro Podróży RAFA - załącznik nr 1 do niniejszej umowy',
        '2. Program zwiedzania (oferta) - załącznik nr 2 do niniejszej umowy',
        '3. Standardowy formularz informacyjny - załącznik nr 3 do niniejszej umowy',
        '4. Warunki ubezpieczenia NNW przy wyjazdach krajowych i KL przy wyjazdach zagranicznych - załącznik nr 4 niniejszej umowy',
    ];

    /**
     * @return list<string>
     */
    public function organizerBlockLines(): array
    {
        return [
            self::ORGANIZER_NAME,
            self::ORGANIZER_ADDRESS.', NIP '.self::ORGANIZER_NIP.', REGON '.self::ORGANIZER_REGON,
            self::ORGANIZER_REGISTRY.', '.self::ORGANIZER_INSURANCE,
        ];
    }

    public function formatMoneyPln(float $amount, int $decimals = 0): string
    {
        return number_format($amount, $decimals, ',', ' ').' zł';
    }

    public function amountInWordsPln(float $amount): string
    {
        $int = (int) round($amount);
        $formatter = new NumberFormatter('pl_PL', NumberFormatter::SPELLOUT);
        $words = $formatter->format($int);
        if ($words === false || $words === '') {
            return $this->formatMoneyPln((float) $int).' brutto';
        }

        $words = mb_strtolower($words, 'UTF-8');
        $words = mb_strtoupper(mb_substr($words, 0, 1, 'UTF-8'), 'UTF-8')
            .mb_substr($words, 1, null, 'UTF-8');

        return $words.' złotych brutto';
    }

    public function bankAccountLine(): string
    {
        return 'Konto organizatora '.self::BANK_NAME.' nr '.self::BANK_ACCOUNT;
    }

    /**
     * OWU zgodne z PDF (obowiązują do umów zawartych po 20 lutego 2026 r.).
     *
     * @return list<array{type: 'title'|'heading'|'p'|'sub', text: string}>
     */
    public function termsOfParticipationBlocks(): array
    {
        return [
            ['type' => 'title', 'text' => 'Ogólne Warunki Uczestnictwa w Imprezach Turystycznych organizowanych przez Biuro Podróży RAFA'],

            ['type' => 'heading', 'text' => '1. Postanowienia ogólne'],
            ['type' => 'p', 'text' => '1. Organizatorem Imprez Turystycznych o których mowa w niniejszych Warunkach Uczestnictwa jest Biuro Podróży RAFA – Rafał Latos (posługujące się również nazwą handlową Biuro Podróży RAFA), ul. Marii Konopnickiej 6, 00-491 Warszawa, NIP 716-250-87-61, REGON 432298189, wpis do rejestru Organizatorów i Pośredników Turystycznych nr 1270, posiadające Gwarancję Ubezpieczeniową Organizatora Turystyki w Towarzystwie Ubezpieczeniowym Signal Iduna S.A.'],
            ['type' => 'p', 'text' => '2. Pojęcia: „impreza turystyczna”, zwana dalej „Imprezą”, „umowa o udział w imprezie turystycznej”, zwana dalej „Umową”, „Podróżny”, „Organizator”, nieuniknione i nadzwyczajne okoliczności oraz „trwały nośnik”; są używane w Warunkach Uczestnictwa, w znaczeniu nadanym im przez przepisy Ustawy o Imprezach Turystycznych i Powiązanych Usługach Turystycznych z dnia 24 listopada 2017 r., zwanej dalej „Ustawą”.'],

            ['type' => 'heading', 'text' => '2. Obowiązki informacyjne wobec Podróżnych'],
            ['type' => 'p', 'text' => '1. Przed zawarciem umowy udziela się Podróżnemu:'],
            ['type' => 'sub', 'text' => 'a. standardowych informacji za pośrednictwem odpowiedniego Standardowego Formularza Informacyjnego.'],
            ['type' => 'sub', 'text' => 'b. informacji określonych w art. 40 ust. 1 i 3 Ustawy, zwanych dalej „informacjami o imprezie".'],
            ['type' => 'p', 'text' => '2. Informacje o imprezie zawarte są: w Ofercie, w Warunkach Uczestnictwa, w informacjach dodatkowych do oferty turystycznej oraz w Umowie/Potwierdzeniu rezerwacji.'],

            ['type' => 'heading', 'text' => '3. Zawarcie umowy o organizację imprezy turystycznej, przedmiot umowy, cena imprezy oraz warunki zapłaty'],
            ['type' => 'p', 'text' => '1. Umowa może zostać zawarta: w formie papierowej w fizycznej obecności stron lub w formie elektronicznej poprzez stronę www.bprafa.pl lub poprzez elektroniczne potwierdzenie rezerwacji wystawione przez Organizatora i przesłane na e-mail wskazany przez Podróżnego. W przypadku zawierania Umowy w formie elektronicznej do zawarcia Umowy dochodzi poprzez dokonanie wpłaty zgodnie z warunkami opisanymi w Umowie i Warunkach Uczestnictwa.'],
            ['type' => 'p', 'text' => '2. Osoba dokonująca rezerwacji musi posiadać pełną zdolność do czynności prawnych. W przypadku zawierania Umowy na rzecz osoby trzeciej (osób trzecich), osoba zawierająca Umowę wskazuje tę osobę (osoby) w momencie zawarcia Umowy. W przypadku rezerwacji grupowych należy podać liczbę uczestników objętych Umową.'],
            ['type' => 'p', 'text' => '3. W przypadku zawierania Umowy na rzecz osoby małoletniej Umowę zawiera rodzic lub opiekun prawny lub osoba posiadająca upoważnienie rodziców lub opiekunów prawnych do zawarcia Umowy. Zawarcie Umowy jest jednoznaczne ze złożeniem oświadczenia o posiadaniu odpowiedniego upoważnienia.'],
            ['type' => 'p', 'text' => '4. W chwili zawarcia Umowy lub niezwłocznie po jej zawarciu udostępnia się Podróżnemu na trwałym nośniku kopię Umowy lub potwierdzenie jej zawarcia. Podróżny jest uprawniony do żądania kopii Umowy w postaci papierowej, jeżeli została zawarta w jednoczesnej fizycznej obecności stron.'],
            ['type' => 'p', 'text' => '5. Na Umowę składa się łącznie treść następujących dokumentów: Umowa lub Potwierdzenie Rezerwacji, Warunki Uczestnictwa, Standardowy Formularz Informacyjny, Oferta zawierająca opis Imprezy wybranej przez Podróżnego i stanowiącej przedmiot Umowy.'],
            ['type' => 'p', 'text' => '6. Zawarcie Umowy jest jednoznaczne z zapoznaniem się z Warunkami Uczestnictwa i akceptacją ich postanowień.'],
            ['type' => 'p', 'text' => '7. Cena określona w Umowie jest ceną brutto i zawiera świadczenia opisane w Ofercie/Opisie Imprezy.'],
            ['type' => 'p', 'text' => '8. Organizator zastrzega sobie prawo zmiany ceny Imprezy Turystycznej (podwyższenia ceny) na skutek bezpośrednich zmian ceny przewozów pasażerskich wynikających ze zmiany kosztów paliwa lub innych źródeł zasilania, wysokości podatków lub opłat od usług turystycznych objętych Umową o udział w Imprezie Turystycznej, nałożonych przez podmioty, które nie biorą bezpośredniego udziału w realizacji Imprezy Turystycznej, w tym podatków turystycznych, opłat lotniskowych lub opłaty za wejście na pokład i zejście na ląd w portach oraz na lotniskach, kursów walut mających znaczenie dla danej Imprezy Turystycznej. W przypadku konieczności podwyższenia ceny Organizator powiadomi o tym fakcie Podróżnego wraz z uzasadnieniem podwyżki wskazując sposób jej naliczenia. W okresie 20 dni przed rozpoczęciem Imprezy Turystycznej, cena opisana w umowie z Podróżnym nie może być podwyższona. W przypadku obniżenia kosztów o których mowa powyżej Podróżny ma prawo do obniżenia ceny Imprezy Turystycznej.'],
            ['type' => 'p', 'text' => '9. Organizator udziela bezpłatnej gwarancji niezmienności ceny dla wszystkich Imprez Turystycznych dla których od dnia zawarcia Umowy do dnia rozpoczęcia Imprezy pozostaje nie więcej niż 90 dni. W pozostałych przypadkach Podróżny może dokupić gwarancję niezmienności ceny za dodatkową opłatą.'],
            ['type' => 'p', 'text' => '10. W dniu zawarcia Umowy, Podróżny dokonuje przedpłaty w wysokości 30% wartości imprezy (za każdego Podróżnego określonego w Umowie) – chyba, że Umowa stanowi inaczej.'],
            ['type' => 'p', 'text' => '11. Wpłaty pozostałej kwoty Podróżny dokonuje najpóźniej na:'],
            ['type' => 'sub', 'text' => 'a. 14 dni przed datą rozpoczęcia Imprezy w przypadku imprez krajowych – chyba, że Umowa stanowi inaczej;'],
            ['type' => 'sub', 'text' => 'b. 30 dni przed datą rozpoczęcia Imprezy w przypadku imprez zagranicznych – chyba, że Umowa stanowi inaczej.'],
            ['type' => 'p', 'text' => '12. W przypadku nie wywiązania się przez Podróżnego z obowiązków wynikających z punktów 3.10 lub 3.11 Organizator zastrzega sobie prawo do anulowania nieopłaconej rezerwacji. Konsekwencją niewywiązania się przez Podróżnego z punktu 3.11 będzie pobranie opłaty za rezygnację zgodnie z postanowieniami punktu 6 Warunków Uczestnictwa.'],

            ['type' => 'heading', 'text' => '4. Ubezpieczenia'],
            ['type' => 'p', 'text' => '1. Wszyscy Podróżni objęci są ubezpieczeniami Signal Iduna Polska TU S.A., ul. Siedmiogrodzka 9, 01-204 Warszawa, infolinia 801 120 120, +48 (22) 50 56 506. Stronami umowy ubezpieczenia jest Podróżny i Ubezpieczyciel.'],
            ['type' => 'p', 'text' => '2. Podróżni uczestniczący w imprezach krajowych objęci są ubezpieczeniem NNW (Następstw Nieszczęśliwych Wypadków) na kwotę 30 000 PLN w wariancie Standard Plus oraz ubezpieczeniem Assistance.'],
            ['type' => 'p', 'text' => '3. Podróżni uczestniczący w imprezach zagranicznych objęci są ubezpieczeniem obejmującym w pakiecie podstawowym: KL (Koszty Leczenia i Assistance) do kwoty 300 000 EUR, NNW (Następstw Nieszczęśliwych Wypadków) do kwoty 35 000 PLN, BP (bagaż podróżny) do kwoty 2500 PLN, OC (Odpowiedzialność Cywilna) do kwoty 60 000 EUR.'],
            ['type' => 'p', 'text' => '4. Ubezpieczenie KL w wersji podstawowej obejmuje ryzyko zaostrzenia choroby przewlekłej.'],
            ['type' => 'p', 'text' => '5. Niepełnoletni Podróżni uczestniczący w zagranicznych wyjazdach dla dzieci i młodzieży podczas których dziecku nie towarzyszy rodzic ani opiekun prawny (wycieczki szkolne, kolonie, obozy itp.), objęci są rozszerzeniem ubezpieczenia o zwrot kosztów transportu i pobytu opiekuna w sytuacji hospitalizacji która nie zakończy się do dnia powrotu do kraju zamieszkania (do kwoty 1000 EUR).'],
            ['type' => 'p', 'text' => '6. Opcjonalne ubezpieczenie Kosztów Rezygnacji nie obejmuje w wersji podstawowej ryzyka zaostrzenia choroby przewlekłej, ryzyka zachorowania na Covid i objęcia kwarantanną – ubezpieczenie można za dopłatą rozszerzyć o te ryzyka.'],
            ['type' => 'p', 'text' => '7. Każdy Podróżny może zawrzeć dodatkowe ubezpieczenie na własny koszt na sumę ubezpieczenia wyższą niż standardowa.'],
            ['type' => 'p', 'text' => '8. Podróżny oświadcza, że został poinformowany o możliwości wykupienia dodatkowego ubezpieczenia kosztów rezygnacji – dodatkowy koszt takiego ubezpieczenia w wersji podstawowej to 3,2% ceny imprezy. Ubezpieczenie takie wykupić należy w dniu zawarcia Umowy (lub jeśli do rozpoczęcia Imprezy pozostało więcej niż 30 dni - w ciągu 14 dni od daty zawarcia Umowy).'],
            ['type' => 'p', 'text' => '9. W przypadku imprez grupowych, najpóźniej na 3 dni robocze przed datą rozpoczęcia Imprezy, należy dostarczyć do Biura Podróży listę uczestników zawierającą następujące dane osobowe osób uczestniczących w wycieczce: imiona, nazwiska, daty urodzenia.'],
            ['type' => 'p', 'text' => '10. Dochodzenie roszczeń wynikających z ubezpieczenia następuje bezpośrednio przez Podróżnego od Ubezpieczyciela.'],
            ['type' => 'p', 'text' => '11. W przypadku zaistnienia szkody podczas Imprezy należy kontaktować się z czynną całą dobę centralą alarmową Signal Iduna tel. + 48 (22) 846 55 26.'],

            ['type' => 'heading', 'text' => '5. Informacje o obowiązujących przepisach paszportowych i wizowych'],
            ['type' => 'p', 'text' => '1. Podróżny oświadcza, że został poinformowany o obowiązujących przepisach paszportowych oraz wizowych na wybranej trasie podróży.'],
            ['type' => 'p', 'text' => '2. Podróżny wyjeżdżający do krajów w obrębie Unii Europejskiej musi posiadać ważny dowód osobisty lub paszport (dokumenty muszą być ważne w momencie wjazdu, pobytu oraz wyjazdu z kraju poza granicami RP, bez określenia minimalnego okresu ważności.).'],
            ['type' => 'p', 'text' => '3. Podróżny wyjeżdżający poza obszar Unii Europejskiej musi posiadać ważny stały paszport (ważny minimum 6 miesięcy od planowanej daty powrotu do Polski).'],
            ['type' => 'p', 'text' => '4. Wymóg posiadania paszportu lub dowodu osobistego dotyczy także dzieci niezależnie od ich wieku. Legitymacja szkolna nie jest dokumentem uprawniającym do przekroczenia granicy.'],
            ['type' => 'p', 'text' => '5. Organizator nie ponosi odpowiedzialności za ewentualne: nie posiadanie przez Podróżnego ważnego paszportu/dowodu osobistego, nie przyznanie Podróżnemu wizy przez placówki konsularne państw, do których obowiązuje ruch wizowy, zatrzymanie jego paszportu oraz za odmowę zgody na wjazd przez służby graniczne państw, w których ostateczną decyzję o przekroczeniu granicy podejmują miejscowe służby imigracyjne lub celne, a także za opóźnienie w wydaniu przez placówkę dyplomatyczną wizy, o której mowa powyżej chyba, że opóźnienie to można przypisać Organizatorowi.'],
            ['type' => 'p', 'text' => '6. Informacje o przepisach paszportowych i wizowych opisane w Ofertach, Umowach i Warunkach Uczestnictwa dotyczą obywateli Polskich. Obywatelom innych państw zaleca się kontakt z odpowiednimi placówkami dyplomatycznymi/konsulatami w celu sprawdzenia obowiązujących ich przepisów paszportowych i wizowych.'],

            ['type' => 'heading', 'text' => '6. Rezygnacja z udziału w Imprezie Turystycznej z inicjatywy Podróżnego/Odstąpienie przez Podróżnego od Umowy/Opłaty za odstąpienie od Umowy'],
            ['type' => 'p', 'text' => '1. Podróżny ma prawo odstąpić od Umowy (zrezygnować z udziału w Imprezie Turystycznej) w każdym czasie przed rozpoczęciem Imprezy.'],
            ['type' => 'p', 'text' => '2. W razie odstąpienia od Umowy (rezygnacji), z zastrzeżeniem wyjątków przewidzianych w Ustawie, Podróżny jest zobowiązany do zapłacenia na rzecz Biura Podróży opłaty za odstąpienie od Umowy (zgodnie z przepisami art. 47 ust. 2 Ustawy), która odpowiada cenie Imprezy Turystycznej pomniejszonej o zaoszczędzone koszty lub wpływy z tytułu alternatywnego wykorzystania danych usług turystycznych.'],
            ['type' => 'p', 'text' => '3. W przypadku odstąpienia przez Podróżnego od Umowy o udział w Imprezie Turystycznej na 30 dni lub więcej niż 30 dni przed jej rozpoczęciem, Organizator pobiera opłatę manipulacyjną w wysokości:'],
            ['type' => 'sub', 'text' => 'a. 50 zł/osoba – w przypadku wycieczki krajowej jednodniowej;'],
            ['type' => 'sub', 'text' => 'b. 100 zł/osoba – w przypadku wycieczki krajowej dwudniowej;'],
            ['type' => 'sub', 'text' => 'c. 250 zł/osoba – w przypadku wycieczki krajowej trzydniowej lub dłuższej;'],
            ['type' => 'sub', 'text' => 'd. 300 zł/osoba – w przypadku wycieczki zagranicznej.'],
            ['type' => 'p', 'text' => '4. Jeżeli rezygnacja następuje na 30 dni lub więcej niż 30 dni przed rozpoczęciem Imprezy, a rzeczywiste koszty poniesione przez Organizatora w związku z jej przygotowaniem przekraczają wysokość opłaty manipulacyjnej wskazanej w pkt 6.3 (w szczególności koszty transportu, biletów lotniczych, rezerwacji noclegów lub innych świadczeń zamówionych na rzecz Podróżnego), Organizator jest uprawniony do potrącenia tych kosztów w rzeczywistej wysokości.'],
            ['type' => 'p', 'text' => '5. W przypadku odstąpienia przez Podróżnego od Umowy o udział w Imprezie Turystycznej w okresie od 29 dni do 1 dnia przed jej rozpoczęciem, a także w dniu rozpoczęcia Imprezy, Organizator pobiera opłatę, o której mowa w pkt 6.2. Jej wysokość ustalana jest na podstawie rzeczywistych kosztów przygotowania Imprezy, przy czym Podróżnemu przysługuje wyłącznie zwrot kosztów, których Organizator uniknął (zaoszczędził) w związku z rezygnacją. Rozliczenie następuje po zakończeniu Imprezy, a zwrot należnych środków dokonany zostaje nie później niż w ciągu 30 dni po jej zakończeniu.'],
            ['type' => 'p', 'text' => '6. Informacyjnie Organizator wskazuje, że historycznie ukształtowana orientacyjna średnia wysokość potrącanych kosztów wynosi:'],
            ['type' => 'sub', 'text' => 'a. przy rezygnacji na 30 dni lub więcej niż 30 dni przed rozpoczęciem Imprezy – koszt rezygnacji zwykle odpowiada opłacie manipulacyjnej określonej w pkt 6.3;'],
            ['type' => 'sub', 'text' => 'b. przy rezygnacji na 29–14 dni przed rozpoczęciem Imprezy - około 50% ceny Imprezy;'],
            ['type' => 'sub', 'text' => 'c. przy rezygnacji na 13–8 dni przed rozpoczęciem Imprezy - około 70% ceny Imprezy;'],
            ['type' => 'sub', 'text' => 'd. przy rezygnacji na 7–1 dni przed rozpoczęciem lub w dniu rozpoczęcia Imprezy - około 95% ceny Imprezy.'],
            ['type' => 'p', 'text' => 'Wskazane wartości mają charakter wyłącznie informacyjny i nie stanowią ryczałtowego określenia kosztów. Do wyliczenia ostatecznie poniesionych kosztów Biuro Podróży może przystąpić dopiero po dacie zakończenia wyjazdu i rozliczeniu kosztów Imprezy, z której Podróżny nie skorzystał.'],

            ['type' => 'heading', 'text' => '7. Odwołanie Imprezy Turystycznej przez Biuro Podróży'],
            ['type' => 'p', 'text' => '1. Organizator może rozwiązać Umowę i dokonać pełnego zwrotu Podróżnemu wpłat dokonanych z tytułu Imprezy, bez dodatkowego odszkodowania lub zadośćuczynienia, jeżeli:'],
            ['type' => 'sub', 'text' => 'a. nie osiągnie zakładanego minimum grupy (zakładana ilość uczestników wskazana w Umowie) i powiadomi Podróżnego o rozwiązaniu Umowy nie później niż 20 dni przed rozpoczęciem Imprezy Turystycznej trwającej ponad 6 dni, 7 dni przed rozpoczęciem Imprezy Turystycznej trwającej 2-6 dni, 48 godzin przed rozpoczęciem Imprezy Turystycznej trwającej krócej niż 2 dni;'],
            ['type' => 'sub', 'text' => 'b. wystąpią nieuniknione i nadzwyczajne okoliczności i powiadomi Uczestnika/Podróżnego o rozwiązaniu Umowy niezwłocznie przed rozpoczęciem imprezy.'],
            ['type' => 'p', 'text' => 'Biuro w ww. przypadkach dokonuje zwrotu wszystkich wpłat dokonanych z tytułu Umowy w terminie 14 dni od dnia jej rozwiązania.'],

            ['type' => 'heading', 'text' => '8. Odpowiedzialność Organizatora'],
            ['type' => 'p', 'text' => '1. Organizator ponosi odpowiedzialność za należyte wykonanie wszystkich usług turystycznych objętych Umową, bez względu na to, czy usługi te mają być wykonane przez Organizatora, czy przez innych dostawców usług turystycznych.'],
            ['type' => 'p', 'text' => '2. Podróżnemu nie przysługuje odszkodowanie lub zadośćuczynienie za niezgodność w przypadku, gdy Organizator udowodni, że:'],
            ['type' => 'sub', 'text' => 'a. winę za niezgodność ponosi Podróżny;'],
            ['type' => 'sub', 'text' => 'b. winę za niezgodność ponosi osoba trzecia, niezwiązana z wykonywaniem usług turystycznych objętych Umową, a niezgodności nie dało się przewidzieć lub uniknąć;'],
            ['type' => 'sub', 'text' => 'c. niezgodność została spowodowana nieuniknionymi i nadzwyczajnymi okolicznościami.'],
            ['type' => 'p', 'text' => '3. W przypadkach innych, niż określone w art. 50 ust. 5 ustawy, Organizator ogranicza odszkodowanie, jakie ma zostać wypłacone przez Organizatora, do trzykrotności ceny Imprezy Turystycznej względem każdego Podróżnego. Ograniczenia tego nie stosuje się w przypadku szkody na osobie lub szkody spowodowanej umyślnie lub w wyniku niedbalstwa.'],
            ['type' => 'p', 'text' => '4. W przypadku gdy Podróżny znalazł się w trudnej sytuacji w związku z wystąpieniem nieuniknionych i nadzwyczajnych okoliczności w rozumieniu art. 4 pkt 15 Ustawy, Biuro Podróży udziela Podróżnemu odpowiedniej pomocy. Organizator może żądać opłaty z tytułu udzielenia pomocy, w szczególności jeżeli trudna sytuacja powstała z wyłącznej winy umyślnej Podróżnego lub w wyniku jego rażącego niedbalstwa.'],
            ['type' => 'p', 'text' => '5. Organizator nie ponosi odpowiedzialności za skutki wynikłe dla Podróżnego z faktu niezgłoszenia się w terminie na miejsce zbiórki bądź zatrzymania przez krajowe czy zagraniczne służby graniczne, celne, policję lub inne władze bądź nie posiadania przez Podróżnego ważnego paszportu, dowodu osobistego lub wizy. W powyższych przypadkach Organizator rozliczy dokonane przez Podróżnego wpłaty według punktu 6.2 Warunków Uczestnictwa.'],

            ['type' => 'heading', 'text' => '9. Odpowiedzialność Podróżnego'],
            ['type' => 'p', 'text' => '1. Podróżny zobowiązany jest przestrzegać przepisów celnych, dewizowych i porządkowych obowiązujących w Polsce, w krajach tranzytowych i w kraju docelowym, jak również zaleceń pilota wycieczki.'],
            ['type' => 'p', 'text' => '2. Za wszelkie zniszczenia, uszkodzenia i inne szkody powstałe z winy Podróżnego w trakcie trwania Imprezy Turystycznej odpowiedzialność (prawną, finansową, odszkodowawczą) ponosi Podróżny.'],
            ['type' => 'p', 'text' => '3. Za szkody wyrządzone podczas Imprezy przez osoby małoletnie odpowiadają rodzice, opiekunowie prawni lub osoby, którym na czas trwania Imprezy została powierzona opieka nad małoletnimi dziećmi.'],

            ['type' => 'heading', 'text' => '10. Przetwarzanie i ochrona danych osobowych'],
            ['type' => 'p', 'text' => '1. Administratorem danych osobowych podanych przez Podróżnych jest Biuro Podróży RAFA – Rafał Latos.'],
            ['type' => 'p', 'text' => '2. Dane osobowe Podróżnych przetwarzane są w celu zawarcia i realizacji Umowy oraz mogą być przekazywane podmiotom uczestniczącym w jej wykonaniu, takim jak m.in. linie lotnicze, przewoźnicy autokarowi i promowi, hotele, zakłady ubezpieczeń czy pilot grupy – wyłącznie w zakresie niezbędnym do realizacji Umowy.'],
            ['type' => 'p', 'text' => '3. Podstawą przetwarzania danych osobowych jest zawarta Umowa.'],
            ['type' => 'p', 'text' => '4. Podanie danych osobowych jest dobrowolne, jednak niezbędne do zawarcia Umowy. W przypadku niepodania danych niemożliwe jest zawarcie Umowy.'],
            ['type' => 'p', 'text' => '5. Dane osobowe Podróżnych nie podlegają zautomatyzowanemu podejmowaniu decyzji ani profilowaniu.'],
            ['type' => 'p', 'text' => '6. W zależności od kierunku podróży, w zakresie niezbędnym do realizacji Umowy, dane osobowe mogą być przekazywane do odbiorców w państwach trzecich (poza Europejskim Obszarem Gospodarczym). Przekazywanie danych odbywa się na podstawie decyzji Komisji Europejskiej stwierdzającej odpowiedni stopień ochrony danych osobowych albo z zastosowaniem odpowiednich zabezpieczeń prawnych, w szczególności standardowych klauzul umownych zatwierdzonych przez Komisję Europejską. Podróżny ma prawo uzyskać informacje o stosowanych zabezpieczeniach oraz kopię danych przekazywanych do państw trzecich, kontaktując się z Administratorem.'],
            ['type' => 'p', 'text' => '7. Dane osobowe będą przechowywane przez okres, w którym osoba, której dane dotyczą może dochodzić roszczeń z tytułu niewykonania lub nienależytego wykonania Umowy.'],
            ['type' => 'p', 'text' => '8. Szczegółowe informacje dotyczące przetwarzania danych osobowych, w tym dane kontaktowe Administratora, przysługujące prawa osób, podstawy prawne przetwarzania oraz informacje o możliwości wniesienia skargi do Prezesa Urzędu Ochrony Danych Osobowych, zawarte są w Polityce prywatności dostępnej na stronie internetowej Administratora.'],

            ['type' => 'heading', 'text' => '11. Reklamacje'],
            ['type' => 'p', 'text' => '1. Jeżeli w trakcie Imprezy Podróżny stwierdza wadliwe wykonywanie Umowy powinien niezwłocznie zawiadomić o tym wykonawcę usługi (pilota) oraz Organizatora. Podróżnemu przysługuje również prawo do złożenia reklamacji w terminie do 30 dni kalendarzowych od daty zakończenia Imprezy, której reklamacja dotyczy. Reklamację złożyć można w siedzibie Organizatora, przekazać pismo reklamacyjne pilotowi wycieczki lub wysłać ją na email: rafa@bprafa.pl'],
            ['type' => 'p', 'text' => '2. Reklamacje rozpatrywane są przez Organizatora bez zbędnej zwłoki, jednak nie później niż w terminie 30 dni kalendarzowych licząc od dnia ich wpływu, przy czym do zachowania terminu wystarczy wysłanie (np. nadanie przesyłki w placówce pocztowej lub wysłanie maila) odpowiedzi przed jego upływem.'],
            ['type' => 'p', 'text' => '3. Pilot wycieczki nie jest uprawniony do uznawania roszczeń Podróżnego.'],
            ['type' => 'p', 'text' => '4. Jeżeli z przyczyn niezależnych od Podróżnego w trakcie trwania danej Imprezy Turystycznej Organizator nie wykonuje przewidzianych w Umowie usług, stanowiących istotną część programu tej Imprezy, wówczas Organizator wykona w ramach tej Imprezy, bez obciążania Podróżnego dodatkowymi kosztami, odpowiednie świadczenie zastępcze. Jeżeli jakość świadczenia zastępczego jest niższa od jakości usługi określonej w Umowie, Podróżny może zażądać odpowiedniego obniżenia ustalonej ceny Imprezy.'],
            ['type' => 'p', 'text' => '5. Podmiotami uprawnionymi do prowadzenia spraw z zakresu postępowań pozasądowych dotyczących usług turystycznych są Inspekcje Handlowe. Wykaz Inspekcji Handlowych znajduje się na stronie UOKiK.'],

            ['type' => 'heading', 'text' => '12. Postanowienia końcowe'],
            ['type' => 'p', 'text' => '1. Ewentualne spory wynikające z tytułu realizacji Umowy będą rozstrzygane polubownie, a w przypadku braku porozumienia przez sądy powszechne właściwe według przepisów Kodeksu Postępowania Cywilnego.'],
            ['type' => 'p', 'text' => '2. W sprawach nie uregulowanych Umową mają zastosowanie przepisy Ustawy o Imprezach Turystycznych i Powiązanych Usługach Turystycznych, Kodeksu Cywilnego, oraz inne przepisy dotyczące ochrony konsumenta w tym rozporządzenie Unii Europejskiej dotyczące konsumentów usług turystycznych.'],
            ['type' => 'p', 'text' => '3. Podróżny może zapoznać się z treścią Ustawy, w tym przepisów powołanych w Warunkach Uczestnictwa, na stronie internetowej www.sejm.gov.pl.'],
            ['type' => 'p', 'text' => '4. Warunki Uczestnictwa obowiązują do umów zawartych po 20 lutego 2026 r.'],
        ];
    }

    /**
     * @return list<array{type: 'title'|'p'|'bullet'|'link'|'footer', text: string}>
     */
    public function standardInformationFormBlocks(): array
    {
        return [
            ['type' => 'title', 'text' => 'STANDARDOWY FORMULARZ INFORMACYJNY DO UMÓW O UDZIAŁ W IMPREZIE TURYSTYCZNEJ'],
            ['type' => 'p', 'text' => 'Zaoferowane Państwu połączenie usług turystycznych stanowi imprezę turystyczną w rozumieniu dyrektywy (UE) 2015/2302. W związku z powyższym będą Państwu przysługiwały wszystkie prawa UE mające zastosowanie do imprez turystycznych. Biuro Podróży RAFA będzie ponosiło pełną odpowiedzialność za należytą realizację całości imprezy turystycznej. Ponadto, zgodnie z wymogami prawa, Biuro Podróży RAFA posiada zabezpieczenie w celu zapewnienia zwrotu Państwu wpłat i, jeżeli transport jest elementem imprezy turystycznej, zapewnienia Państwa powrotu do kraju w przypadku, gdyby Biuro Podróży RAFA stało się niewypłacalne.'],
            ['type' => 'p', 'text' => 'Najważniejsze prawa zgodnie z dyrektywą (UE) 2015/2302:'],
            ['type' => 'bullet', 'text' => 'Przed zawarciem umowy o udział w imprezie turystycznej podróżni otrzymają wszystkie niezbędne informacje na temat imprezy turystycznej.'],
            ['type' => 'bullet', 'text' => 'Zawsze co najmniej jeden przedsiębiorca ponosi odpowiedzialność za należyte wykonanie wszystkich usług turystycznych objętych umową.'],
            ['type' => 'bullet', 'text' => 'Podróżni otrzymują awaryjny numer telefonu lub dane punktu kontaktowego, dzięki którym mogą skontaktować się z organizatorem turystyki lub agentem turystycznym.'],
            ['type' => 'bullet', 'text' => 'Podróżni mogą przenieść imprezę turystyczną na inną osobę, powiadamiając o tym w rozsądnym terminie, z zastrzeżeniem ewentualnych dodatkowych kosztów.'],
            ['type' => 'bullet', 'text' => 'Cena imprezy turystycznej może zostać podwyższona jedynie wtedy, gdy wzrosną określone koszty (na przykład koszty paliwa) i zostało to wyraźnie przewidziane w umowie; w żadnym przypadku podwyżka ceny nie może nastąpić później niż 20 dni przed rozpoczęciem imprezy turystycznej. Jeżeli podwyżka ceny przekracza 8% ceny imprezy turystycznej, podróżny może rozwiązać umowę. Jeżeli organizator turystyki zastrzega sobie prawo do podwyższenia ceny, podróżny ma prawo do obniżki ceny, jeżeli obniżyły się odpowiednie koszty.'],
            ['type' => 'bullet', 'text' => 'Podróżni mogą rozwiązać umowę bez ponoszenia jakiejkolwiek opłaty za rozwiązanie i uzyskać pełen zwrot wszelkich wpłat, jeżeli jeden z istotnych elementów imprezy turystycznej, inny niż cena, zmieni się w znaczący sposób. Jeżeli przedsiębiorca odpowiedzialny za imprezę turystyczną odwoła ją przed jej rozpoczęciem, podróżni mają prawo do zwrotu wpłat oraz w stosownych przypadkach do rekompensaty.'],
            ['type' => 'bullet', 'text' => 'W wyjątkowych okolicznościach – na przykład jeżeli w docelowym miejscu podróży występują poważne problemy związane z bezpieczeństwem, które mogą wpłynąć na imprezę turystyczną – podróżni mogą, przed rozpoczęciem imprezy turystycznej, rozwiązać umowę bez ponoszenia jakiejkolwiek opłaty za rozwiązanie. Ponadto podróżni mogą w każdym momencie przed rozpoczęciem imprezy turystycznej rozwiązać umowę za odpowiednią i możliwą do uzasadnienia opłatą.'],
            ['type' => 'bullet', 'text' => 'Jeżeli po rozpoczęciu imprezy turystycznej jej znaczące elementy nie mogą zostać zrealizowane zgodnie z umową, będą musiały zostać zaproponowane, bez dodatkowych kosztów, odpowiednie alternatywne usługi. W przypadku gdy usługi nie są świadczone zgodnie z umową, co istotnie wpływa na realizację imprezy turystycznej, a organizator turystyki nie zdoła usunąć problemu, podróżni mogą rozwiązać umowę bez opłaty za rozwiązanie.'],
            ['type' => 'bullet', 'text' => 'Podróżni są również uprawnieni do otrzymania obniżki ceny lub rekompensaty za szkodę w przypadku niewykonania lub nienależytego wykonania usług turystycznych. Organizator turystyki musi zapewnić pomoc podróżnemu, który znajdzie się w trudnej sytuacji.'],
            ['type' => 'bullet', 'text' => 'W przypadku gdy organizator turystyki stanie się niewypłacalny, wpłaty zostaną zwrócone. Jeżeli organizator turystyki stanie się niewypłacalny po rozpoczęciu imprezy turystycznej i jeżeli impreza turystyczna obejmuje transport, zapewniony jest powrót podróżnych do kraju. Biuro Podróży RAFA wykupiło w Towarzystwie Ubezpieczeń Signal Iduna zabezpieczenie na wypadek niewypłacalności. Podróżni mogą kontaktować się z tym podmiotem lub, w odpowiednich przypadkach, z właściwym organem: Urząd Marszałkowski Województwa Mazowieckiego, Departament Kultury, Sportu i Turystyki, ul. Bertolta Brechta 3, 03-472 Warszawa, e-mail: dkpit@mazovia.pl, tel. (+48 22) 5979-501, (+48 22) 5979-54 jeżeli z powodu niewypłacalności Biura Podróży RAFA, dojdzie do odmowy świadczenia usług.'],
            ['type' => 'p', 'text' => 'Dyrektywa (UE) 2015/2302:'],
            ['type' => 'link', 'text' => 'https://eur-lex.europa.eu/legal-content/PL/TXT/PDF/?uri=CELEX:32015L2302&from=PL'],
            ['type' => 'p', 'text' => 'Przetransponowana do prawa krajowego:'],
            ['type' => 'link', 'text' => 'http://prawo.sejm.gov.pl/isap.nsf/download.xsp/WDU20170002361/O/D20172361.pdf'],
            ['type' => 'footer', 'text' => 'Dokument wygenerowany elektronicznie nie wymaga pięczęci ani podpisu'],
        ];
    }
}
