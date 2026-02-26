<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PlaceDistance;
use Illuminate\Support\Facades\DB;

class ClearPlaceDistances extends Command
{
    protected $signature = 'db:clear-place-distances {--force : Wymuś usunięcie bez potwierdzenia}';
    protected $description = 'Czyści wszystkie rekordy z tabeli place_distances';

    public function handle()
    {
        try {
            // Sprawdź liczbę rekordów przed usunięciem
            $count = PlaceDistance::count();

            if ($count === 0) {
                $this->info('Tabela place_distances jest już pusta.');
                return 0;
            }

            $this->info("Znaleziono {$count} rekordów w tabeli place_distances.");

            // Poproś o potwierdzenie, chyba że użyto flagi --force
            if (!$this->option('force')) {
                if (!$this->confirm('Czy na pewno chcesz usunąć wszystkie rekordy z tabeli place_distances?')) {
                    $this->info('Operacja anulowana.');
                    return 0;
                }
            }

            // Wyczyść tabelę
            DB::beginTransaction();

            try {
                PlaceDistance::truncate();

                DB::commit();

                $this->info("✅ Pomyślnie wyczyszczono tabelę place_distances. Usunięto {$count} rekordów.");
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Błąd podczas czyszczenia tabeli: " . $e->getMessage());
            return 1;
        }
    }
}
