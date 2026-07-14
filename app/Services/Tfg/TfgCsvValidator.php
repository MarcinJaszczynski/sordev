<?php

namespace App\Services\Tfg;

use App\Models\Contract;
use App\Models\TfgDictionaryItem;
use Illuminate\Support\Collection;

/**
 * Validates contracts against the official TFG CSV "Wykaz umów" constraints
 * before export, returning readable per-contract error messages.
 */
class TfgCsvValidator
{
    public function maxContracts(): int
    {
        return (int) config('tfg.csv.max_contracts', 5000);
    }

    public function maxLocations(): int
    {
        return (int) config('tfg.csv.max_locations', 5);
    }

    public function maxTransports(): int
    {
        return (int) config('tfg.csv.max_transports', 3);
    }

    /**
     * @return array<int, string> contract id => readable error list, plus a global key for file-level issues
     */
    public function validateCollection(Collection $contracts, string $operation): array
    {
        $errors = [];

        if ($contracts->count() > $this->maxContracts()) {
            $errors['_file'] = ['Plik może zawierać maksymalnie '.$this->maxContracts().' umów (obecnie: '.$contracts->count().').'];
        }

        foreach ($contracts as $contract) {
            $contractErrors = $this->collectErrors($contract, $operation);
            if ($contractErrors !== []) {
                $errors[$contract->id] = $contractErrors;
            }
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    public function collectErrors(Contract $contract, string $operation): array
    {
        $contract->loadMissing(['variants.locations', 'variants.transports', 'payments', 'refunds']);

        $errors = [];

        if (blank($contract->contract_number) && blank($contract->reservation_number)) {
            $errors[] = 'Brak numeru umowy / rezerwacji (NrUmowyRezerwacji).';
        }

        if ($operation === Contract::OP_USUNIECIE) {
            return $errors;
        }

        if ($operation === Contract::OP_ROZWIAZANIE) {
            if (blank($contract->tfg_termination_date)) {
                $errors[] = 'Brak daty rozwiązania umowy (DataRozwiazaniaUmowy).';
            }

            return $errors;
        }

        // NOWEDANE / KOREKTA - pełna walidacja danych umowy.
        if (! TfgDictionaryItem::isValidCode(TfgDictionaryItem::TYPE_SUBJECT, $contract->subject_code)) {
            $errors[] = 'Nieprawidłowy lub brakujący przedmiot umowy (PrzedmiotUmowy).';
        }

        if (blank($contract->contract_date)) {
            $errors[] = 'Brak daty zawarcia umowy (DataZawarciaUmowy).';
        }

        if (! TfgDictionaryItem::isValidCode(TfgDictionaryItem::TYPE_PAYMENT_METHOD, $contract->payment_method_code)) {
            $errors[] = 'Nieprawidłowy lub brakujący sposób przyjmowania wpłat (SposobPrzyjmowaniaWplat).';
        }

        if (blank($contract->total_price) || (float) $contract->total_price <= 0) {
            $errors[] = 'Brak łącznej ceny usług (LacznaCenaUslug1).';
        }

        if (! TfgDictionaryItem::isValidCode(TfgDictionaryItem::TYPE_CURRENCY, strtoupper((string) $contract->currency))) {
            $errors[] = 'Nieprawidłowa waluta usług (WalutaUslug1): '.$contract->currency.'.';
        }

        $variants = $contract->variants;
        if ($variants->count() === 0) {
            $errors[] = 'Umowa musi mieć jeden wariant podróży (termin, lokalizacje, transport).';
        } elseif ($variants->count() > 1) {
            $errors[] = 'Format CSV obsługuje wyłącznie jeden wariant podróży na umowę (obecnie: '.$variants->count().'). Użyj API lub podziel umowę.';
        }

        $variant = $variants->first();
        if ($variant) {
            $errors = array_merge($errors, $this->validateVariant($variant));
        }

        foreach ($contract->payments as $payment) {
            if (! TfgDictionaryItem::isValidCode(TfgDictionaryItem::TYPE_CURRENCY, strtoupper((string) $payment->currency))) {
                $errors[] = 'Nieprawidłowa waluta wpłaty: '.$payment->currency.'.';
            }
        }

        foreach ($contract->refunds as $refund) {
            if (! TfgDictionaryItem::isValidCode(TfgDictionaryItem::TYPE_CURRENCY, strtoupper((string) $refund->currency))) {
                $errors[] = 'Nieprawidłowa waluta zwrotu: '.$refund->currency.'.';
            }
        }

        if ($operation === Contract::OP_KOREKTA
            && $contract->correction_reason === 'ZMIANA'
            && blank($contract->tfg_change_date)) {
            $errors[] = 'Korekta typu "Zmiana" wymaga daty zmiany umowy (DataZmianyUmowy).';
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    private function validateVariant($variant): array
    {
        $errors = [];

        if (blank($variant->starts_at)) {
            $errors[] = 'Brak terminu realizacji od (TerminRealizacjiOd).';
        }

        if (blank($variant->ends_at)) {
            $errors[] = 'Brak terminu realizacji do (TerminRealizacjiDo).';
        }

        if ((int) $variant->travelers_count < 1) {
            $errors[] = 'Liczba podróżnych musi być większa od zera (LiczbaPodroznych).';
        }

        $locations = $variant->locations;
        if ($locations->count() === 0) {
            $errors[] = 'Wymagana co najmniej jedna lokalizacja realizacji.';
        }

        if ($locations->count() > $this->maxLocations()) {
            $errors[] = 'Maksymalnie '.$this->maxLocations().' lokalizacji na umowę.';
        }

        foreach ($locations as $index => $location) {
            $position = $index + 1;

            if (! TfgDictionaryItem::isValidCode(TfgDictionaryItem::TYPE_SCOPE, $location->scope_type)) {
                $errors[] = "Lokalizacja {$position}: nieprawidłowy zakres terytorialny (ZakresTerytorialny{$position}).";
            }

            if (! TfgDictionaryItem::isValidCode(TfgDictionaryItem::TYPE_COUNTRY, $location->country_code)) {
                $errors[] = "Lokalizacja {$position}: nieprawidłowy kod kraju (KrajRealizacjiUmowy{$position}).";
            }
        }

        $transports = $variant->transports;
        if ($transports->count() > $this->maxTransports()) {
            $errors[] = 'Maksymalnie '.$this->maxTransports().' środków transportu na umowę.';
        }

        foreach ($transports as $index => $transport) {
            $position = $index + 1;

            if (! TfgDictionaryItem::isValidCode(TfgDictionaryItem::TYPE_TRANSPORT, $transport->transport_code)) {
                $errors[] = "Transport {$position}: nieprawidłowy rodzaj transportu (RodzajSrodkaTransportu{$position}).";

                continue;
            }

            if (TfgDictionaryItem::requiresIcao((string) $transport->transport_code)) {
                $icao = (array) ($transport->icao_codes ?? []);
                $first = $icao[0] ?? null;

                if (blank($first)) {
                    $errors[] = "Transport {$position}: kod lotniska docelowego jest wymagany dla transportu lotniczego (KodLotniskaDocelowego{$position}).";
                } elseif (! preg_match('/^[A-Z]{4}$/', (string) $first)) {
                    $errors[] = "Transport {$position}: nieprawidłowy kod lotniska ICAO: {$first}.";
                }
            }
        }

        return $errors;
    }
}
