<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class GuideBlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $posts = [
            [
                'title' => 'Jak zorganizować wycieczkę szkolną krok po kroku?',
                'slug' => 'jak-zorganizowac-wycieczke-szkolna-krok-po-kroku',
                'excerpt' => 'Prosty plan działania dla nauczyciela: od pomysłu na kierunek po spokojny wyjazd z dobrze przygotowaną grupą.',
                'featured_image' => 'event-templates/CNK4.JPG',
                'featured_image_alt' => 'Uczniowie na wycieczce szkolnej w Centrum Nauki Kopernik',
                'content' => <<<HTML
<p>Jeśli organizujesz wycieczkę szkolną pierwszy raz, spokojnie — da się to ogarnąć bez stresu. Najlepiej iść krok po kroku.</p>
<h3>1. Ustal cel i budżet</h3>
<p>Na start odpowiedz sobie na dwa pytania: po co jedziemy i ile realnie możemy wydać na osobę. To od razu zawęża wybór.</p>
<h3>2. Wybierz kierunek i termin</h3>
<p>Dopasuj długość wyjazdu do wieku grupy i kalendarza szkoły. Im wcześniej rezerwujesz, tym większy wybór terminów i noclegów.</p>
<h3>3. Zbierz informacje od rodziców/opiekunów</h3>
<p>Przygotuj listę uczestników, zgody, informacje zdrowotne i potrzeby żywieniowe. To mały detal, który później oszczędza dużo nerwów.</p>
<h3>4. Potwierdź zakres świadczeń</h3>
<p>Zawsze sprawdź, co dokładnie jest w cenie: transport, noclegi, wyżywienie, bilety, opieka pilota i ubezpieczenie.</p>
<h3>5. Komunikuj się z grupą</h3>
<p>Na koniec przekaż rodzicom i uczniom jasny plan: zbiórka, bagaż, kontakt i zasady bezpieczeństwa. Dobra komunikacja robi ogromną różnicę.</p>
HTML,
                'guide_category' => 'wycieczki-szkolne',
            ],
            [
                'title' => 'Jak zaplanować wyjazd integracyjny dla firmy?',
                'slug' => 'jak-zaplanowac-wyjazd-integracyjny-dla-firmy',
                'excerpt' => 'Jak zaplanować firmową integrację bez chaosu: cel, plan dnia, logistyka i sensowne podsumowanie po powrocie.',
                'featured_image' => 'event-templates/Integracja-529x295.webp',
                'featured_image_alt' => 'Integracja firmowa na wyjeździe grupowym',
                'content' => <<<HTML
<p>Dobry wyjazd firmowy to coś więcej niż hotel i kolacja. Najlepiej działa wtedy, gdy integracja idzie w parze z konkretnym celem.</p>
<h3>1. Określ cel wyjazdu</h3>
<p>Zastanów się, co ma zostać po wyjeździe: lepsza współpraca, onboarding nowych osób, a może domknięcie ważnego etapu projektu.</p>
<h3>2. Dobierz format</h3>
<p>Krótki wyjazd 1-2 dni sprawdzi się przy intensywnej agendzie. Dłuższy program daje więcej czasu na networking i spokojniejsze tempo.</p>
<h3>3. Zadbaj o logistykę</h3>
<p>Transport, nocleg i harmonogram to fundament. Jeśli te elementy są dopięte, uczestnicy mogą skupić się na samym wyjeździe.</p>
<h3>4. Ustal plan dnia</h3>
<p>Warto połączyć część merytoryczną (warsztat, podsumowanie) z integracją i czasem na luz. Dzięki temu wyjazd nie jest przeładowany.</p>
<h3>5. Podsumuj efekty</h3>
<p>Po powrocie zbierz krótki feedback od zespołu. To najlepszy materiał do zaplanowania kolejnego, jeszcze lepszego wyjazdu.</p>
HTML,
                'guide_category' => 'wyjazdy-firmowe',
            ],
            [
                'title' => 'Co powinno zawierać ubezpieczenie na wycieczkę szkolną?',
                'slug' => 'co-powinno-zawierac-ubezpieczenie-na-wycieczke-szkolna',
                'excerpt' => 'Krótko i konkretnie: jakie elementy ubezpieczenia naprawdę warto sprawdzić, zanim grupa ruszy w trasę.',
                'featured_image' => 'event-templates/Malbork_12.jpg',
                'featured_image_alt' => 'Grupa na wycieczce szkolnej przy Zamku w Malborku',
                'content' => <<<HTML
<p>Przy ubezpieczeniu najważniejsze jest jedno: ma realnie pomagać wtedy, kiedy coś pójdzie nie tak. Dlatego warto sprawdzić kilka kluczowych punktów.</p>
<h3>NNW</h3>
<p>Podstawowa ochrona na wypadek nieszczęśliwego zdarzenia podczas wyjazdu.</p>
<h3>KL i Assistance (wyjazdy zagraniczne)</h3>
<p>W wyjazdach zagranicznych to absolutna podstawa: pokrycie kosztów leczenia i szybka pomoc organizacyjna.</p>
<h3>Ubezpieczenie kosztów rezygnacji</h3>
<p>Bardzo przydatne, gdy uczestnik musi zrezygnować z powodów losowych i chcesz ograniczyć straty finansowe.</p>
<h3>Jak czytać OWU?</h3>
<p>Zawsze sprawdź limity, wyłączenia i sposób zgłoszenia szkody. Te trzy rzeczy najczęściej decydują, czy polisa faktycznie działa.</p>
HTML,
                'guide_category' => 'bezpieczenstwo-i-ubezpieczenia',
            ],
            [
                'title' => 'Checklist nauczyciela przed wyjazdem szkolnym',
                'slug' => 'checklist-nauczyciela-przed-wyjazdem-szkolnym',
                'excerpt' => 'Gotowa lista „przed wyjazdem”, która pomaga uniknąć nerwów: dokumenty, kontakt z rodzicami i podział opieki.',
                'featured_image' => 'event-templates/Torun.jpeg',
                'featured_image_alt' => 'Toruń — popularny kierunek wycieczek szkolnych',
                'content' => <<<HTML
<p>Dobra checklista przed wyjazdem to mniej stresu i mniej gaszenia pożarów na ostatnią chwilę.</p>
<ul>
<li>Lista uczestników z kontaktami awaryjnymi.</li>
<li>Zgody i wymagane dokumenty uczestników.</li>
<li>Podział opieki między nauczycieli/opiekunów.</li>
<li>Informacja do rodziców: godzina zbiórki, bagaż, zasady kontaktu.</li>
<li>Weryfikacja planu dnia, noclegów i posiłków.</li>
<li>Przypomnienie zasad bezpieczeństwa przed wyjazdem.</li>
</ul>
<p>W praktyce taka lista oszczędza mnóstwo czasu i pomaga spokojnie poprowadzić cały wyjazd.</p>
HTML,
                'guide_category' => 'organizacja-grupy',
            ],
            [
                'title' => 'Ile kosztuje wycieczka szkolna i od czego zależy cena?',
                'slug' => 'ile-kosztuje-wycieczka-szkolna',
                'excerpt' => 'Cena wycieczki szkolnej zależy od kilku prostych elementów: trasy, liczby dni, grupy i standardu świadczeń.',
                'featured_image' => 'event-templates/sukiennice.jpg',
                'featured_image_alt' => 'Sukiennice w Krakowie — kierunek wycieczek szkolnych',
                'content' => <<<HTML
<p>To jedno z najczęstszych pytań rodziców i nauczycieli. I słusznie, bo budżet zwykle ustawia cały plan wyjazdu.</p>
<h3>Największy wpływ na cenę mają:</h3>
<ul>
<li>długość wyjazdu,</li>
<li>kierunek i odległość,</li>
<li>liczba uczestników,</li>
<li>standard noclegów i wyżywienia,</li>
<li>bilety wstępu oraz atrakcje dodatkowe.</li>
</ul>
<p>Najłatwiej porównać oferty wtedy, gdy dokładnie wiadomo, co zawiera cena. Dlatego warto zawsze patrzeć nie tylko na kwotę, ale też na zakres świadczeń.</p>
HTML,
                'guide_category' => 'wycieczki-szkolne',
            ],
            [
                'title' => 'Jak wybrać autokar na wycieczkę szkolną?',
                'slug' => 'jak-wybrac-autokar-na-wycieczke-szkolna',
                'excerpt' => 'Bezpieczeństwo, komfort i organizacja przejazdu — na co naprawdę warto zwrócić uwagę przy wyborze autokaru.',
                'featured_image' => 'event-templates/Berlin-4-dni.webp',
                'featured_image_alt' => 'Wyjazd grupowy autokarem — Berlin',
                'content' => <<<HTML
<p>Autokar to nie tylko środek transportu. Przy wyjeździe szkolnym to jeden z najważniejszych elementów całej organizacji.</p>
<h3>Na co zwrócić uwagę?</h3>
<ul>
<li>stan techniczny pojazdu i doświadczenie przewoźnika,</li>
<li>liczbę miejsc dopasowaną do grupy,</li>
<li>czas przejazdu i planowane postoje,</li>
<li>komfort uczniów i opiekunów podczas dłuższej trasy.</li>
</ul>
<p>W praktyce najlepiej współpracować z organizatorem, który już ma sprawdzonych przewoźników i potrafi dobrać transport do konkretnego programu.</p>
HTML,
                'guide_category' => 'transport',
            ],
            [
                'title' => 'Co zabrać na zieloną szkołę?',
                'slug' => 'co-zabrac-na-zielona-szkole',
                'excerpt' => 'Krótka lista rzeczy, które naprawdę przydają się na zielonej szkole — bez pakowania połowy domu.',
                'featured_image' => 'event-templates/Agroturystyka-dla-najmłodszych-2048x1366.jpg',
                'featured_image_alt' => 'Zielona szkoła — aktywności dla dzieci na wyjeździe',
                'content' => <<<HTML
<p>Pakowanie na zieloną szkołę nie musi być chaotyczne. Najlepiej postawić na rzeczy praktyczne i dostosowane do programu wyjazdu.</p>
<h3>Najczęściej przydają się:</h3>
<ul>
<li>wygodne ubrania na zmianę,</li>
<li>kurtka przeciwdeszczowa i buty terenowe,</li>
<li>mały plecak na wyjścia dzienne,</li>
<li>leki przyjmowane na stałe i podstawowe środki higieniczne,</li>
<li>dokumenty wymagane przez szkołę lub organizatora.</li>
</ul>
<p>Zamiast bardzo długiej listy lepiej przygotować prostą rozpiskę pod konkretny program: góry, morze, miasto albo aktywności terenowe.</p>
HTML,
                'guide_category' => 'organizacja-grupy',
            ],
            [
                'title' => 'Jak zorganizować wyjazd integracyjny dla firmy bez chaosu?',
                'slug' => 'jak-zorganizowac-wyjazd-integracyjny-dla-firmy-bez-chaosu',
                'excerpt' => 'Plan, logistyka i komunikacja — trzy rzeczy, które decydują, czy wyjazd firmowy będzie udany.',
                'featured_image' => 'event-templates/2G5A0139.jpg',
                'featured_image_alt' => 'Grupa na wyjeździe integracyjnym',
                'content' => <<<HTML
<p>Największy problem przy wyjazdach firmowych rzadko dotyczy samego hotelu. Najczęściej chaos pojawia się wtedy, gdy brakuje jasnego planu i komunikacji.</p>
<h3>Co pomaga?</h3>
<ul>
<li>jedna osoba odpowiedzialna za decyzje organizacyjne,</li>
<li>krótki i czytelny harmonogram dla uczestników,</li>
<li>dopasowanie programu do charakteru zespołu,</li>
<li>rozsądne proporcje między częścią formalną i integracyjną.</li>
</ul>
<p>Im prostsza organizacja od strony uczestnika, tym lepszy odbiór całego wyjazdu.</p>
HTML,
                'guide_category' => 'wyjazdy-firmowe',
            ],
            [
                'title' => 'Jak wybrać kierunek wycieczki szkolnej?',
                'slug' => 'jak-wybrac-kierunek-wycieczki-szkolnej',
                'excerpt' => 'Kierunek warto dobierać nie tylko do marzeń grupy, ale też do wieku uczniów, budżetu i czasu przejazdu.',
                'featured_image' => 'event-templates/tatry.jpg',
                'featured_image_alt' => 'Tatry — jeden z kierunków wycieczek szkolnych',
                'content' => <<<HTML
<p>Dobry kierunek to taki, który naprawdę pasuje do grupy. Nie zawsze „najdalej” znaczy „najlepiej”.</p>
<h3>Co warto wziąć pod uwagę?</h3>
<ul>
<li>wiek uczniów i ich tempo zwiedzania,</li>
<li>czas przejazdu,</li>
<li>budżet na osobę,</li>
<li>czy wyjazd ma być bardziej edukacyjny, integracyjny czy aktywny.</li>
</ul>
<p>W praktyce najlepiej sprawdzają się kierunki, które dają równowagę między atrakcjami, logistyką i komfortem grupy.</p>
HTML,
                'guide_category' => 'wycieczki-szkolne',
            ],
            [
                'title' => 'Jak przygotować rodziców do wycieczki szkolnej?',
                'slug' => 'jak-przygotowac-rodzicow-do-wycieczki-szkolnej',
                'excerpt' => 'Spokojna komunikacja z rodzicami przed wyjazdem potrafi zdjąć więcej napięcia niż najlepiej rozpisany plan.',
                'featured_image' => 'event-templates/Dzieciece-figle-1536x1024.webp',
                'featured_image_alt' => 'Dzieci podczas wycieczki szkolnej',
                'content' => <<<HTML
<p>Rodzice najczęściej chcą po prostu wiedzieć, że ich dzieci są dobrze zaopiekowane i że organizacja jest przemyślana.</p>
<h3>Warto przekazać wcześniej:</h3>
<ul>
<li>plan wyjazdu i najważniejsze punkty programu,</li>
<li>godzinę i miejsce zbiórki,</li>
<li>listę rzeczy do zabrania,</li>
<li>zasady kontaktu podczas wyjazdu,</li>
<li>informacje o bezpieczeństwie i opiece.</li>
</ul>
<p>Im jaśniejsza komunikacja przed wyjazdem, tym mniej pytań i stresu w ostatnich dniach przed wyjazdem.</p>
HTML,
                'guide_category' => 'organizacja-grupy',
            ],
            [
                'title' => 'Czy warto wybrać zieloną szkołę zamiast krótkiej wycieczki?',
                'slug' => 'czy-warto-wybrac-zielona-szkole-zamiast-krotkiej-wycieczki',
                'excerpt' => 'Zielona szkoła daje więcej czasu na integrację i spokojniejszy program, ale nie zawsze będzie najlepszym wyborem dla każdej grupy.',
                'featured_image' => 'event-templates/Mazury-dla-aktywnych-4-1024x683.jpg',
                'featured_image_alt' => 'Aktywny wyjazd na Mazurach — zielona szkoła',
                'content' => <<<HTML
<p>Zielona szkoła sprawdza się wtedy, gdy zależy Ci nie tylko na zwiedzaniu, ale też na integracji i wspólnym rytmie grupy przez kilka dni.</p>
<h3>Kiedy to dobry wybór?</h3>
<ul>
<li>gdy grupa potrzebuje więcej czasu na integrację,</li>
<li>gdy program ma łączyć naukę z aktywnością,</li>
<li>gdy chcesz ograniczyć pośpiech typowy dla krótkich wyjazdów.</li>
</ul>
<p>Z kolei krótka wycieczka lepiej sprawdzi się przy mniejszym budżecie albo wtedy, gdy celem jest jedno konkretne miejsce lub temat.</p>
HTML,
                'guide_category' => 'wycieczki-szkolne',
            ],
            [
                'title' => 'Jak ułożyć program wyjazdu firmowego, żeby nie był nudny?',
                'slug' => 'jak-ulozyc-program-wyjazdu-firmowego',
                'excerpt' => 'Najlepsze programy firmowe nie są przeładowane — dają rytm dnia, ale zostawiają też miejsce na naturalną integrację.',
                'featured_image' => 'event-templates/kajak.webp',
                'featured_image_alt' => 'Aktywny program wyjazdu firmowego — spływ kajakowy',
                'content' => <<<HTML
<p>Najczęstszy błąd? Zbyt sztywny plan albo odwrotnie — całkowity brak struktury. Dobrze ułożony program powinien prowadzić uczestników, ale ich nie męczyć.</p>
<h3>Co zwykle działa najlepiej?</h3>
<ul>
<li>krótki, czytelny blok merytoryczny,</li>
<li>jedna lub dwie aktywności integracyjne,</li>
<li>czas na rozmowy bez formalnej agendy,</li>
<li>wieczór z luźniejszą atmosferą, ale bez przesady.</li>
</ul>
<p>Lepiej zakończyć wyjazd z poczuciem niedosytu niż z wrażeniem, że program był zbyt ciężki.</p>
HTML,
                'guide_category' => 'wyjazdy-firmowe',
            ],
        ];

        foreach ($posts as $index => $post) {
            BlogPost::query()->updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'content' => $post['content'],
                    'content_type' => 'poradnik',
                    'guide_category' => $post['guide_category'],
                    'featured_image' => $post['featured_image'] ?? null,
                    'featured_image_alt' => $post['featured_image_alt'] ?? null,
                    'status' => 'active',
                    'is_published' => true,
                    'published_at' => Carbon::now()->subDays(count($posts) - $index),
                ]
            );
        }
    }
}

