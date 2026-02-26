<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serwis do pobierania kursów walut z NBP API i przeliczania szablonów imprez
 */
class CurrencyRateService
{
    /**
     * Aktualizuje kursy walut z NBP API i przelicza szablony imprez
     */
    public function updateRates(): int
    {
        try {
            // Pobierz kursy z NBP API (tabela A - kursy średnie)
            $response = Http::get('https://api.nbp.pl/api/exchangerates/tables/A?format=json');
            
            if (!$response->successful()) {
                throw new \Exception('Nie udało się pobrać kursów z NBP API');
            }
            
            $data = $response->json();
            
            if (empty($data) || !isset($data[0]['rates'])) {
                throw new \Exception('Nieprawidłowa odpowiedź z NBP API');
            }
            
            $rates = $data[0]['rates'];
            $updatedCount = 0;
            
            // Aktualizuj kursy walut
            foreach ($rates as $rate) {
                $updated = Currency::where('symbol', $rate['code'])
                    ->update([
                        'exchange_rate' => $rate['mid'],
                        'updated_at' => now(),
                    ]);
                
                if ($updated > 0) {
                    $updatedCount++;
                    Log::info("Zaktualizowano kurs waluty {$rate['code']}: {$rate['mid']}");
                }
            }
            
            // Dodaj PLN jeśli nie istnieje (kurs 1.0)
            Currency::updateOrCreate(
                ['symbol' => 'PLN'],
                [
                    'name' => 'Polski złoty',
                    'exchange_rate' => 1.0000,
                    'updated_at' => now(),
                ]
            );
            
            // Przelicz szablony imprez
            $this->recalculateEventTemplates();
            
            Log::info("Zaktualizowano {$updatedCount} kursów walut i przeliczono szablony imprez");
            
            return $updatedCount;
            
        } catch (\Exception $e) {
            Log::error('Błąd aktualizacji kursów walut: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Przelicza wszystkie szablony imprez z aktualnym kursami walut
     */
    private function recalculateEventTemplates(): void
    {
        try {
            // Pobierz wszystkie punkty programu z walutami
            $programPoints = EventTemplateProgramPoint::with('currency')
                ->whereNotNull('currency_id')
                ->whereNotNull('unit_price')
                ->get();
            
            $recalculatedCount = 0;
            
            foreach ($programPoints as $point) {
                if ($point->currency && $point->currency->exchange_rate) {
                    // Przelicz cenę na PLN
                    $oldPricePln = $point->price_pln;
                    $newPricePln = $point->unit_price * $point->currency->exchange_rate;
                    
                    if ($oldPricePln != $newPricePln) {
                        $point->update(['price_pln' => $newPricePln]);
                        $recalculatedCount++;
                        
                        Log::info("Przeliczono punkt programu ID {$point->id}: {$oldPricePln} PLN → {$newPricePln} PLN");
                    }
                }
            }
            
            Log::info("Przeliczono {$recalculatedCount} punktów programu");
            
        } catch (\Exception $e) {
            Log::error('Błąd przeliczania szablonów imprez: ' . $e->getMessage());
            throw $e;
        }
    }
}
