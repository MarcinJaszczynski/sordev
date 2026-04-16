<?php

namespace App\Services;

use App\Models\ContractTemplate;

class AgreementTemplateRenderer
{
    public function render(?ContractTemplate $template, array $payload): string
    {
        $content = trim((string) ($template?->content ?? ''));

        if ($content === '') {
            $content = $this->defaultTemplate();
        }

        $replacements = [
            '[NUMER_UMOWY]' => (string) ($payload['agreement_number'] ?? '—'),
            '[DATA_UMOWY]' => (string) ($payload['agreement_date'] ?? '—'),
            '[TYP_UMOWY]' => (string) ($payload['agreement_type_label'] ?? '—'),
            '[NAZWA_IMPREZY]' => (string) ($payload['event_name'] ?? '—'),
            '[DATA_START]' => (string) ($payload['event_start_date'] ?? '—'),
            '[DATA_KONIEC]' => (string) ($payload['event_end_date'] ?? '—'),
            '[KLIENT]' => (string) ($payload['customer_name'] ?? '—'),
            '[EMAIL]' => (string) ($payload['customer_email'] ?? '—'),
            '[TELEFON]' => (string) ($payload['customer_phone'] ?? '—'),
            '[ZAMAWIAJACY_INSTYTUCJA]' => (string) ($payload['ordering_institution'] ?? '—'),
            '[ZAMAWIAJACY_IMIE_NAZWISKO]' => (string) ($payload['ordering_person'] ?? '—'),
            '[ZAMAWIAJACY_EMAIL]' => (string) ($payload['ordering_email'] ?? '—'),
            '[ZAMAWIAJACY_TELEFON]' => (string) ($payload['ordering_phone'] ?? '—'),
            '[ZAMAWIAJACY_ADRES]' => (string) ($payload['signer_address_full'] ?? '—'),
            '[OPIEKUN]' => (string) ($payload['ordering_person'] ?? '—'),
            '[PODPISUJACY_IMIE_NAZWISKO]' => (string) ($payload['signer_name'] ?? '—'),
            '[PODPISUJACY_EMAIL]' => (string) ($payload['signer_email'] ?? '—'),
            '[PODPISUJACY_TELEFON]' => (string) ($payload['signer_phone'] ?? '—'),
            '[PODPISUJACY_ADRES_ULICA]' => (string) ($payload['signer_address_street'] ?? '—'),
            '[PODPISUJACY_ADRES_NUMER]' => (string) ($payload['signer_address_number'] ?? '—'),
            '[PODPISUJACY_KOD_POCZTOWY]' => (string) ($payload['signer_postal_code'] ?? '—'),
            '[PODPISUJACY_MIASTO]' => (string) ($payload['signer_city'] ?? '—'),
            '[PODPISUJACY_WOJEWODZTWO]' => (string) ($payload['signer_province'] ?? '—'),
            '[UCZESTNIK]' => (string) ($payload['participant_name'] ?? '—'),
            '[PODOPIECZNY]' => (string) ($payload['participant_name'] ?? '—'),
            '[DATA_URODZENIA]' => (string) ($payload['participant_birth_date'] ?? '—'),
            '[UCZESTNIK_EMAIL]' => (string) ($payload['participant_email'] ?? '—'),
            '[UCZESTNIK_TELEFON]' => (string) ($payload['participant_phone'] ?? '—'),
            '[LICZBA_OSOB]' => (string) ($payload['participant_count'] ?? '—'),
            '[KWOTA]' => (string) ($payload['amount_due'] ?? '0,00'),
            '[WALUTA]' => (string) ($payload['currency'] ?? 'PLN'),
            '[CENA_JEDNOSTKOWA]' => (string) ($payload['amount_per_person'] ?? '—'),
            '[DODATKOWE_UBEZPIECZENIE]' => (string) ($payload['travel_insurance_label'] ?? 'Nie wybrano'),
            '[MIEJSCE_WYJAZDU]' => (string) ($payload['departure_place'] ?? '—'),
            '[DATA_WYJAZDU]' => (string) ($payload['departure_date'] ?? '—'),
            '[GODZINA_WYJAZDU]' => (string) ($payload['departure_time'] ?? '—'),
            '[MIEJSCE_POWROTU]' => (string) ($payload['return_place'] ?? '—'),
            '[DATA_POWROTU]' => (string) ($payload['return_date'] ?? '—'),
            '[GODZINA_POWROTU]' => (string) ($payload['return_time'] ?? '—'),
            '[ORGANIZATOR_NAZWA]' => (string) ($payload['organizer_name'] ?? '—'),
            '[ORGANIZATOR_ADRES_1]' => (string) ($payload['organizer_address_line_1'] ?? '—'),
            '[ORGANIZATOR_ADRES_2]' => (string) ($payload['organizer_address_line_2'] ?? '—'),
            '[ORGANIZATOR_EMAIL]' => (string) ($payload['organizer_email'] ?? '—'),
            '[ORGANIZATOR_TELEFON]' => (string) ($payload['organizer_phone'] ?? '—'),
            '[REFERENCJA_REZERWACJI]' => (string) ($payload['booking_reference'] ?? '—'),
            '[LINK_UMOWY]' => (string) ($payload['public_link'] ?? '—'),
        ];

        // Backward compatibility: support legacy moustache placeholders.
        foreach ($payload as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $replacements['{{' . $key . '}}'] = (string) ($value ?? '—');
        }

        return strtr($content, $replacements);
    }

    protected function defaultTemplate(): string
    {
        return implode("\n", [
            'UMOWA [NUMER_UMOWY]',
            'Data: [DATA_UMOWY]',
            'Typ: [TYP_UMOWY]',
            '',
            'Impreza: [NAZWA_IMPREZY]',
            'Termin: [DATA_START] - [DATA_KONIEC]',
            'Liczba uczestników: [LICZBA_OSOB]',
            'Uczestnik: [UCZESTNIK]',
            'Data urodzenia uczestnika: [DATA_URODZENIA]',
            'Referencja: [REFERENCJA_REZERWACJI]',
            '',
            'Klient: [KLIENT]',
            'Email: [EMAIL]',
            'Telefon: [TELEFON]',
            '',
            'Kwota do zapłaty: [KWOTA] [WALUTA]',
            '',
            'Link do zawarcia i opłacenia umowy:',
            '[LINK_UMOWY]',
        ]);
    }
}
