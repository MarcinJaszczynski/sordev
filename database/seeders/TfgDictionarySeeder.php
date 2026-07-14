<?php

namespace Database\Seeders;

use App\Models\TfgDictionaryItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TfgDictionarySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCoreDictionaries();
        $this->seedCurrencies();
        $this->seedCountries();
        $this->seedAirports();
    }

    /**
     * Small hand-maintained dictionaries aligned with the official TFG codes
     * (see pliki/tfg2/Wykaz_umow_szablony_do_eksportu_csv_23062026.xlsx).
     */
    private function seedCoreDictionaries(): void
    {
        $items = [
            // Przedmioty umów (COM-SL-0008-PRZEDM_UMOW)
            ['type' => TfgDictionaryItem::TYPE_SUBJECT, 'code' => 'IT', 'label' => 'Impreza turystyczna', 'sort_order' => 1],
            ['type' => TfgDictionaryItem::TYPE_SUBJECT, 'code' => 'PUT', 'label' => 'Powiązane usługi turystyczne', 'sort_order' => 2],

            // Sposoby płatności (COM-SL-0104-SPOS-PLATN)
            ['type' => TfgDictionaryItem::TYPE_PAYMENT_METHOD, 'code' => 'WPLATAPRZED', 'label' => 'Część lub całość wpłaty przed realizacją umowy', 'sort_order' => 1],
            ['type' => TfgDictionaryItem::TYPE_PAYMENT_METHOD, 'code' => 'WPLATAPO', 'label' => 'Cała wpłata po realizacji umowy', 'sort_order' => 2],

            // Rodzaje transportu (COM-SL-0007-RODZAJ_TR)
            ['type' => TfgDictionaryItem::TYPE_TRANSPORT, 'code' => 'LOTCZART', 'label' => 'Lotniczny czarterowy', 'sort_order' => 1, 'meta' => ['requires_icao' => true]],
            ['type' => TfgDictionaryItem::TYPE_TRANSPORT, 'code' => 'LOTNCZART', 'label' => 'Lotniczy inny niż czarterowy', 'sort_order' => 2, 'meta' => ['requires_icao' => true]],
            ['type' => TfgDictionaryItem::TYPE_TRANSPORT, 'code' => 'NLOT', 'label' => 'Inny niż lotniczy', 'sort_order' => 3, 'meta' => ['requires_icao' => false]],
            ['type' => TfgDictionaryItem::TYPE_TRANSPORT, 'code' => 'BRAK', 'label' => 'Brak transportu', 'sort_order' => 4, 'meta' => ['requires_icao' => false]],

            // Zakresy terytorialne (COM-SL-0005-ZAK_TERYT)
            ['type' => TfgDictionaryItem::TYPE_SCOPE, 'code' => 'PLISAS', 'label' => 'Polska i państwa sąsiadujące z Polską', 'sort_order' => 1],
            ['type' => TfgDictionaryItem::TYPE_SCOPE, 'code' => 'EUR', 'label' => 'Państwa europejskie', 'sort_order' => 2],
            ['type' => TfgDictionaryItem::TYPE_SCOPE, 'code' => 'POZAEUR', 'label' => 'Państwa pozaeuropejskie', 'sort_order' => 3],

            // Typy operacji (COM-SL-0101-TYP-OPERACJI)
            ['type' => TfgDictionaryItem::TYPE_OPERATION, 'code' => 'NOWEDANE', 'label' => 'Nowe dane', 'sort_order' => 1],
            ['type' => TfgDictionaryItem::TYPE_OPERATION, 'code' => 'KOREKTA', 'label' => 'Korekta', 'sort_order' => 2],
            ['type' => TfgDictionaryItem::TYPE_OPERATION, 'code' => 'ROZWIAZANIE', 'label' => 'Rozwiązanie', 'sort_order' => 3],
            ['type' => TfgDictionaryItem::TYPE_OPERATION, 'code' => 'USUNIECIE', 'label' => 'Usunięcie', 'sort_order' => 4],

            // Powody korekty (COM-SL-0105-POW-KOREKTY)
            ['type' => TfgDictionaryItem::TYPE_CORRECTION_REASON, 'code' => 'BLAD', 'label' => 'Błąd', 'sort_order' => 1],
            ['type' => TfgDictionaryItem::TYPE_CORRECTION_REASON, 'code' => 'ZMIANA', 'label' => 'Zmiana w umowie', 'sort_order' => 2],
        ];

        foreach ($items as $item) {
            TfgDictionaryItem::query()->updateOrCreate(
                ['type' => $item['type'], 'code' => $item['code']],
                [
                    'label' => $item['label'],
                    'is_active' => true,
                    'sort_order' => $item['sort_order'],
                    'meta' => $item['meta'] ?? null,
                ]
            );
        }

        // Deactivate any legacy codes that are not part of the official TFG set,
        // so dropdowns only offer valid values.
        $allowed = collect($items)->groupBy('type')->map(fn ($group) => $group->pluck('code')->all());

        foreach ($allowed as $type => $codes) {
            TfgDictionaryItem::query()
                ->where('type', $type)
                ->whereNotIn('code', $codes)
                ->update(['is_active' => false]);
        }
    }

    private function seedCurrencies(): void
    {
        $currencies = require database_path('data/tfg_currencies.php');

        $rows = [];
        $order = 0;
        foreach ($currencies as $code) {
            $rows[] = $this->dictionaryRow(TfgDictionaryItem::TYPE_CURRENCY, $code, $code, ++$order);
        }

        $this->upsertRows($rows);
    }

    private function seedCountries(): void
    {
        $countries = require database_path('data/tfg_countries.php');

        $rows = [];
        $order = 0;
        foreach ($countries as [$code, $scope, $label]) {
            $rows[] = $this->dictionaryRow(
                TfgDictionaryItem::TYPE_COUNTRY,
                $code,
                $label,
                ++$order,
                ['scope' => $scope],
            );
        }

        $this->upsertRows($rows);
    }

    private function seedAirports(): void
    {
        $airports = require database_path('data/tfg_airports.php');

        $rows = [];
        $order = 0;
        foreach ($airports as [$code, $label]) {
            $rows[] = $this->dictionaryRow(TfgDictionaryItem::TYPE_AIRPORT, $code, $label, ++$order);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->upsertRows($chunk);
        }
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @return array<string, mixed>
     */
    private function dictionaryRow(string $type, string $code, string $label, int $sortOrder, ?array $meta = null): array
    {
        return [
            'type' => $type,
            'code' => $code,
            'label' => $label,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'meta' => $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function upsertRows(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        DB::table('tfg_dictionary_items')->upsert(
            $rows,
            ['type', 'code'],
            ['label', 'is_active', 'sort_order', 'meta', 'updated_at'],
        );
    }
}
