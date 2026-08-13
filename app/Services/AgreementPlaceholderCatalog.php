<?php

namespace App\Services;

/**
 * Jedno źródło prawdy dla systemowych znaczników umów [TAG].
 *
 * @phpstan-type PlaceholderDef array{tag: string, label: string, group: string, payload_key: string}
 */
final class AgreementPlaceholderCatalog
{
    public const GROUP_AGREEMENT = 'Umowa';

    public const GROUP_EVENT = 'Impreza';

    public const GROUP_TRANSPORT = 'Transport';

    public const GROUP_CUSTOMER = 'Klient / zamawiający';

    public const GROUP_PARTICIPANT = 'Uczestnik';

    public const GROUP_FINANCE = 'Finanse';

    public const GROUP_ORGANIZER = 'Organizator';

    public const GROUP_ANNEX = 'Aneks';

    /**
     * @return list<PlaceholderDef>
     */
    public function definitions(): array
    {
        return [
            ['tag' => '[NUMER_UMOWY]', 'label' => 'Numer umowy', 'group' => self::GROUP_AGREEMENT, 'payload_key' => 'agreement_number'],
            ['tag' => '[DATA_UMOWY]', 'label' => 'Data umowy', 'group' => self::GROUP_AGREEMENT, 'payload_key' => 'agreement_date'],
            ['tag' => '[TYP_UMOWY]', 'label' => 'Typ umowy', 'group' => self::GROUP_AGREEMENT, 'payload_key' => 'agreement_type_label'],
            ['tag' => '[LINK_UMOWY]', 'label' => 'Link publiczny do umowy', 'group' => self::GROUP_AGREEMENT, 'payload_key' => 'public_link'],
            ['tag' => '[REFERENCJA_REZERWACJI]', 'label' => 'Referencja rezerwacji', 'group' => self::GROUP_AGREEMENT, 'payload_key' => 'booking_reference'],

            ['tag' => '[NAZWA_IMPREZY]', 'label' => 'Nazwa imprezy', 'group' => self::GROUP_EVENT, 'payload_key' => 'event_name'],
            ['tag' => '[DATA_START]', 'label' => 'Data rozpoczęcia', 'group' => self::GROUP_EVENT, 'payload_key' => 'event_start_date'],
            ['tag' => '[DATA_KONIEC]', 'label' => 'Data zakończenia', 'group' => self::GROUP_EVENT, 'payload_key' => 'event_end_date'],
            ['tag' => '[LICZBA_OSOB]', 'label' => 'Liczba osób', 'group' => self::GROUP_EVENT, 'payload_key' => 'participant_count'],
            ['tag' => '[DODATKOWE_UBEZPIECZENIE]', 'label' => 'Dodatkowe ubezpieczenie', 'group' => self::GROUP_EVENT, 'payload_key' => 'travel_insurance_label'],

            ['tag' => '[MIEJSCE_WYJAZDU]', 'label' => 'Miejsce wyjazdu', 'group' => self::GROUP_TRANSPORT, 'payload_key' => 'departure_place'],
            ['tag' => '[DATA_WYJAZDU]', 'label' => 'Data wyjazdu', 'group' => self::GROUP_TRANSPORT, 'payload_key' => 'departure_date'],
            ['tag' => '[GODZINA_WYJAZDU]', 'label' => 'Godzina wyjazdu', 'group' => self::GROUP_TRANSPORT, 'payload_key' => 'departure_time'],
            ['tag' => '[MIEJSCE_POWROTU]', 'label' => 'Miejsce powrotu', 'group' => self::GROUP_TRANSPORT, 'payload_key' => 'return_place'],
            ['tag' => '[DATA_POWROTU]', 'label' => 'Data powrotu', 'group' => self::GROUP_TRANSPORT, 'payload_key' => 'return_date'],
            ['tag' => '[GODZINA_POWROTU]', 'label' => 'Godzina powrotu', 'group' => self::GROUP_TRANSPORT, 'payload_key' => 'return_time'],

            ['tag' => '[KLIENT]', 'label' => 'Klient', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'customer_name'],
            ['tag' => '[EMAIL]', 'label' => 'Email klienta', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'customer_email'],
            ['tag' => '[TELEFON]', 'label' => 'Telefon klienta', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'customer_phone'],
            ['tag' => '[ZAMAWIAJACY_INSTYTUCJA]', 'label' => 'Zamawiający — instytucja', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'ordering_institution'],
            ['tag' => '[ZAMAWIAJACY_IMIE_NAZWISKO]', 'label' => 'Zamawiający — imię i nazwisko', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'ordering_person'],
            ['tag' => '[ZAMAWIAJACY_EMAIL]', 'label' => 'Zamawiający — email', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'ordering_email'],
            ['tag' => '[ZAMAWIAJACY_TELEFON]', 'label' => 'Zamawiający — telefon', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'ordering_phone'],
            ['tag' => '[ZAMAWIAJACY_ADRES]', 'label' => 'Zamawiający — adres', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'signer_address_full'],
            ['tag' => '[OPIEKUN]', 'label' => 'Opiekun', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'ordering_person'],
            ['tag' => '[PODPISUJACY_IMIE_NAZWISKO]', 'label' => 'Podpisujący — imię i nazwisko', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'signer_name'],
            ['tag' => '[PODPISUJACY_EMAIL]', 'label' => 'Podpisujący — email', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'signer_email'],
            ['tag' => '[PODPISUJACY_TELEFON]', 'label' => 'Podpisujący — telefon', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'signer_phone'],
            ['tag' => '[PODPISUJACY_ADRES_ULICA]', 'label' => 'Podpisujący — ulica', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'signer_address_street'],
            ['tag' => '[PODPISUJACY_ADRES_NUMER]', 'label' => 'Podpisujący — numer', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'signer_address_number'],
            ['tag' => '[PODPISUJACY_KOD_POCZTOWY]', 'label' => 'Podpisujący — kod pocztowy', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'signer_postal_code'],
            ['tag' => '[PODPISUJACY_MIASTO]', 'label' => 'Podpisujący — miasto', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'signer_city'],
            ['tag' => '[PODPISUJACY_WOJEWODZTWO]', 'label' => 'Podpisujący — województwo', 'group' => self::GROUP_CUSTOMER, 'payload_key' => 'signer_province'],

            ['tag' => '[UCZESTNIK]', 'label' => 'Uczestnik', 'group' => self::GROUP_PARTICIPANT, 'payload_key' => 'participant_name'],
            ['tag' => '[PODOPIECZNY]', 'label' => 'Podopieczny', 'group' => self::GROUP_PARTICIPANT, 'payload_key' => 'participant_name'],
            ['tag' => '[DATA_URODZENIA]', 'label' => 'Data urodzenia uczestnika', 'group' => self::GROUP_PARTICIPANT, 'payload_key' => 'participant_birth_date'],
            ['tag' => '[UCZESTNIK_EMAIL]', 'label' => 'Email uczestnika', 'group' => self::GROUP_PARTICIPANT, 'payload_key' => 'participant_email'],
            ['tag' => '[UCZESTNIK_TELEFON]', 'label' => 'Telefon uczestnika', 'group' => self::GROUP_PARTICIPANT, 'payload_key' => 'participant_phone'],

            ['tag' => '[KWOTA]', 'label' => 'Kwota do zapłaty', 'group' => self::GROUP_FINANCE, 'payload_key' => 'amount_due'],
            ['tag' => '[WALUTA]', 'label' => 'Waluta', 'group' => self::GROUP_FINANCE, 'payload_key' => 'currency'],
            ['tag' => '[CENA_JEDNOSTKOWA]', 'label' => 'Cena jednostkowa', 'group' => self::GROUP_FINANCE, 'payload_key' => 'unit_price'],
            ['tag' => '[SCHEMAT_PLATNOSCI]', 'label' => 'Schemat płatności', 'group' => self::GROUP_FINANCE, 'payload_key' => 'payment_scheme_label'],
            ['tag' => '[HARMONOGRAM_PLATNOSCI]', 'label' => 'Harmonogram płatności', 'group' => self::GROUP_FINANCE, 'payload_key' => 'payment_schedule_text'],

            ['tag' => '[ORGANIZATOR_NAZWA]', 'label' => 'Nazwa organizatora / firmy', 'group' => self::GROUP_ORGANIZER, 'payload_key' => 'organizer_name'],
            ['tag' => '[ORGANIZATOR_ADRES_1]', 'label' => 'Adres organizatora (linia 1)', 'group' => self::GROUP_ORGANIZER, 'payload_key' => 'organizer_address_line_1'],
            ['tag' => '[ORGANIZATOR_ADRES_2]', 'label' => 'Adres organizatora (linia 2)', 'group' => self::GROUP_ORGANIZER, 'payload_key' => 'organizer_address_line_2'],
            ['tag' => '[ORGANIZATOR_EMAIL]', 'label' => 'Email organizatora', 'group' => self::GROUP_ORGANIZER, 'payload_key' => 'organizer_email'],
            ['tag' => '[ORGANIZATOR_TELEFON]', 'label' => 'Telefon organizatora', 'group' => self::GROUP_ORGANIZER, 'payload_key' => 'organizer_phone'],

            ['tag' => '[UMOWA_BAZOWA]', 'label' => 'Numer umowy bazowej (aneks)', 'group' => self::GROUP_ANNEX, 'payload_key' => 'parent_agreement_number'],
            ['tag' => '[RODZAJE_ZMIAN_ANEKSU]', 'label' => 'Rodzaje zmian aneksu', 'group' => self::GROUP_ANNEX, 'payload_key' => 'annex_change_types'],
            ['tag' => '[OPIS_ZMIAN_PROGRAMU]', 'label' => 'Opis zmian programu', 'group' => self::GROUP_ANNEX, 'payload_key' => 'annex_program_change_notes'],
            ['tag' => '[PROGRAM_ANEKSU]', 'label' => 'Program aneksu (tekst)', 'group' => self::GROUP_ANNEX, 'payload_key' => 'annex_program_text'],
        ];
    }

    /**
     * @return array<string, string> tag => label (płaska lista do selecta)
     */
    public function optionsForSelect(): array
    {
        $options = [];

        foreach ($this->definitions() as $definition) {
            $options[$definition['tag']] = $definition['group'].': '.$definition['label'].' '.$definition['tag'];
        }

        return $options;
    }

    /**
     * @return array<string, array<string, string>> group => [tag => label]
     */
    public function optionsGrouped(): array
    {
        $grouped = [];

        foreach ($this->definitions() as $definition) {
            $grouped[$definition['group']][$definition['tag']] = $definition['label'].' '.$definition['tag'];
        }

        return $grouped;
    }

    public function helperText(): string
    {
        $tags = array_column($this->definitions(), 'tag');

        return 'Dostępne znaczniki systemowe: '.implode(', ', $tags).'. Własne pola zdefiniuj poniżej i wstaw jako [klucz].';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public function replacementsFromPayload(array $payload): array
    {
        $replacements = [];

        foreach ($this->definitions() as $definition) {
            $key = $definition['payload_key'];
            $value = $payload[$key] ?? '—';
            $replacements[$definition['tag']] = is_scalar($value) || $value === null
                ? (string) ($value ?? '—')
                : '—';
        }

        // Alias historyczny — cena jednostkowa może też brać amount_per_person.
        if (($replacements['[CENA_JEDNOSTKOWA]'] ?? '—') === '—' && isset($payload['amount_per_person'])) {
            $replacements['[CENA_JEDNOSTKOWA]'] = (string) $payload['amount_per_person'];
        }

        return $replacements;
    }

    /**
     * Przykładowy payload do podglądu szablonu w panelu.
     *
     * @return array<string, string>
     */
    public function samplePayload(): array
    {
        return [
            'agreement_number' => 'UMOWA-PRZYKŁAD-001',
            'agreement_date' => now()->format('d.m.Y'),
            'agreement_type_label' => 'Grupowa',
            'event_name' => 'Wycieczka przykładowa',
            'event_start_date' => now()->addDays(30)->format('d.m.Y'),
            'event_end_date' => now()->addDays(33)->format('d.m.Y'),
            'customer_name' => 'Szkoła Przykładowa',
            'customer_email' => 'kontakt@przyklad.pl',
            'customer_phone' => '+48 123 456 789',
            'ordering_institution' => 'Szkoła Przykładowa',
            'ordering_person' => 'Jan Kowalski',
            'ordering_email' => 'jan.kowalski@przyklad.pl',
            'ordering_phone' => '+48 111 222 333',
            'signer_address_full' => 'ul. Przykładowa 1, 00-001 Warszawa',
            'signer_name' => 'Jan Kowalski',
            'signer_email' => 'jan.kowalski@przyklad.pl',
            'signer_phone' => '+48 111 222 333',
            'signer_address_street' => 'ul. Przykładowa',
            'signer_address_number' => '1',
            'signer_postal_code' => '00-001',
            'signer_city' => 'Warszawa',
            'signer_province' => 'mazowieckie',
            'participant_name' => 'Anna Nowak',
            'participant_birth_date' => '01.01.2012',
            'participant_email' => 'anna@przyklad.pl',
            'participant_phone' => '+48 999 888 777',
            'participant_count' => '40',
            'amount_due' => '12 000,00',
            'amount_per_person' => '300,00',
            'unit_price' => '300,00',
            'currency' => 'PLN',
            'travel_insurance_label' => 'Tak',
            'departure_place' => 'Warszawa, pl. Defilad 1',
            'departure_date' => now()->addDays(30)->format('d.m.Y'),
            'departure_time' => '07:30',
            'return_place' => 'Warszawa, pl. Defilad 1',
            'return_date' => now()->addDays(33)->format('d.m.Y'),
            'return_time' => '18:00',
            'organizer_name' => (string) config('company.name', config('app.name', 'Organizator')),
            'organizer_address_line_1' => (string) config('company.address_line_1', '—'),
            'organizer_address_line_2' => (string) config('company.address_line_2', '—'),
            'organizer_email' => (string) config('company.email', '—'),
            'organizer_phone' => (string) config('company.phone', '—'),
            'booking_reference' => 'REF-PRZYKŁAD',
            'public_link' => url('/umowa/przyklad'),
            'parent_agreement_number' => 'UMOWA-BAZOWA-001',
            'annex_change_types' => 'Zmiana ceny, Zmiana terminu',
            'annex_program_change_notes' => 'Dodano wizytę w muzeum.',
            'annex_program_text' => 'Dzień 1: Zwiedzanie miasta',
            'payment_scheme_label' => 'W transzach',
            'payment_schedule_text' => "1. Zaliczka: 4 000,00 PLN — 01.09.2026\n2. Reszta: 8 000,00 PLN — 01.10.2026",
        ];
    }
}
