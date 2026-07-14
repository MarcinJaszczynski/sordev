<?php

namespace App\Services\Tfg;

use App\Models\Contract;
use Illuminate\Support\Collection;

/**
 * Builds the official TFG "Wykaz umów" CSV for each of the four operations
 * (NOWEDANE, KOREKTA, ROZWIAZANIE, USUNIECIE).
 */
class TfgWykazCsvExporter
{
    public function __construct(
        private readonly TfgCsvWriter $writer = new TfgCsvWriter,
    ) {}

    /**
     * @return array{filename: string, content: string, contracts_count: int, rows_count: int}
     */
    public function export(Collection $contracts, string $operation): array
    {
        $contracts = $contracts->values();
        $header = $this->header($operation);

        $rows = [];
        foreach ($contracts as $contract) {
            foreach ($this->rowsForContract($contract, $operation) as $row) {
                $rows[] = $row;
            }
        }

        return [
            'filename' => $this->filename($operation),
            'content' => $this->writer->build($header, $rows),
            'contracts_count' => $contracts->count(),
            'rows_count' => count($rows),
        ];
    }

    public function filename(string $operation): string
    {
        return match ($operation) {
            Contract::OP_NOWEDANE => 'nowe_dane.csv',
            Contract::OP_KOREKTA => 'korekta.csv',
            Contract::OP_ROZWIAZANIE => 'rozwiazanie.csv',
            Contract::OP_USUNIECIE => 'usuniecie.csv',
            default => 'wykaz_umow.csv',
        };
    }

    /**
     * @return array<int, string>
     */
    public function header(string $operation): array
    {
        return match ($operation) {
            Contract::OP_NOWEDANE => array_merge(['NrUmowyRezerwacji'], $this->coreHeader()),
            Contract::OP_KOREKTA => array_merge(
                ['NrUmowyRezerwacji', 'PoprzedniNrUmowyRezerwacji'],
                $this->coreHeader(),
                ['DataZmianyUmowy'],
            ),
            Contract::OP_ROZWIAZANIE => ['NrUmowyRezerwacji', 'DataRozwiazaniaUmowy'],
            Contract::OP_USUNIECIE => ['NrUmowyRezerwacji'],
            default => ['NrUmowyRezerwacji'],
        };
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function rowsForContract(Contract $contract, string $operation): array
    {
        $contract->loadMissing(['variants.locations', 'variants.transports', 'payments', 'refunds']);
        $number = $this->contractNumber($contract);

        if ($operation === Contract::OP_USUNIECIE) {
            return [[$number]];
        }

        if ($operation === Contract::OP_ROZWIAZANIE) {
            return [[$number, $this->writer->formatDate($contract->tfg_termination_date)]];
        }

        $static = $this->staticValues($contract);
        $events = $this->paymentEvents($contract);

        if ($events === []) {
            $events[] = ['', '', '', ''];
        }

        $rows = [];
        foreach ($events as $event) {
            if ($operation === Contract::OP_KOREKTA) {
                $changeDate = $contract->correction_reason === 'ZMIANA'
                    ? $this->writer->formatDate($contract->tfg_change_date)
                    : '';

                $rows[] = array_merge([$number, $number], $static, $event, [$changeDate]);

                continue;
            }

            $rows[] = array_merge([$number], $static, $event);
        }

        return $rows;
    }

    /**
     * Header for the columns shared by NOWEDANE and KOREKTA (PrzedmiotUmowy ... WalutaWplatyWalutaZwrotu).
     *
     * @return array<int, string>
     */
    private function coreHeader(): array
    {
        $columns = [
            'PrzedmiotUmowy',
            'DataZawarciaUmowy',
            'TerminRealizacjiOd',
            'TerminRealizacjiDo',
            'LiczbaPodroznych',
        ];

        for ($i = 1; $i <= 5; $i++) {
            $columns[] = "ZakresTerytorialny{$i}";
            $columns[] = "KrajRealizacjiUmowy{$i}";
            $columns[] = "MiejscowoscRealizacjiUmowy{$i}";
        }

        for ($i = 1; $i <= 3; $i++) {
            $columns[] = "RodzajSrodkaTransportu{$i}";
            $columns[] = "KodLotniskaDocelowego{$i}";
        }

        for ($i = 1; $i <= 3; $i++) {
            $columns[] = "LacznaCenaUslug{$i}";
            $columns[] = "WalutaUslug{$i}";
        }

        $columns[] = 'SposobPrzyjmowaniaWplat';
        $columns[] = 'WplataZwrot';
        $columns[] = 'DataWplatyDataZwrotu';
        $columns[] = 'KwotaWplatyKwotaZwrotu';
        $columns[] = 'WalutaWplatyWalutaZwrotu';

        return $columns;
    }

    /**
     * Per-contract constant values (PrzedmiotUmowy ... SposobPrzyjmowaniaWplat).
     *
     * @return array<int, string>
     */
    private function staticValues(Contract $contract): array
    {
        $variant = $contract->variants->first();

        $values = [
            (string) $contract->subject_code,
            $this->writer->formatDate($contract->contract_date),
            $this->writer->formatDate($variant?->starts_at),
            $this->writer->formatDate($variant?->ends_at),
            $variant ? (string) (int) $variant->travelers_count : '',
        ];

        $locations = $variant ? $variant->locations->values() : collect();
        for ($i = 0; $i < 5; $i++) {
            $location = $locations->get($i);
            $values[] = (string) ($location->scope_type ?? '');
            $values[] = (string) ($location->country_code ?? '');
            $values[] = (string) ($location->locality ?? '');
        }

        $transports = $variant ? $variant->transports->values() : collect();
        for ($i = 0; $i < 3; $i++) {
            $transport = $transports->get($i);
            $values[] = (string) ($transport->transport_code ?? '');
            $icao = (array) ($transport->icao_codes ?? []);
            $values[] = (string) ($icao[0] ?? '');
        }

        // Single price -> slot 1; slots 2 and 3 stay empty (per project decision).
        $values[] = $this->writer->formatAmount($contract->total_price);
        $values[] = strtoupper((string) $contract->currency);
        $values[] = '';
        $values[] = '';
        $values[] = '';
        $values[] = '';

        $values[] = (string) $contract->payment_method_code;

        return $values;
    }

    /**
     * One entry per payment ("wpłata") and refund ("zwrot").
     *
     * @return array<int, array<int, string>>
     */
    private function paymentEvents(Contract $contract): array
    {
        $events = [];

        foreach ($contract->payments as $payment) {
            $events[] = [
                'wpłata',
                $this->writer->formatDate($payment->paid_at),
                $this->writer->formatAmount($payment->amount),
                strtoupper((string) $payment->currency),
            ];
        }

        foreach ($contract->refunds as $refund) {
            $events[] = [
                'zwrot',
                $this->writer->formatDate($refund->refunded_at),
                $this->writer->formatAmount($refund->amount),
                strtoupper((string) $refund->currency),
            ];
        }

        return $events;
    }

    private function contractNumber(Contract $contract): string
    {
        return (string) ($contract->contract_number ?: $contract->reservation_number);
    }
}
