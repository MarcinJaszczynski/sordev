<?php

namespace App\Console\Commands;

use App\Models\Contractor;
use App\Models\ContractorLocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillContractorLocationsCommand extends Command
{
    protected $signature = 'contractors:backfill-locations {--dry-run : Tylko podgląd bez zapisu}';

    protected $description = 'Utwórz domyślne miejsce prowadzenia z adresu rozliczeniowego dla kontrahentów z włączoną flagą wielu oddziałów';

    public function handle(): int
    {
        if (! Schema::hasTable('contractor_locations') || ! Schema::hasColumn('contractors', 'uses_business_locations')) {
            $this->warn('Brak tabel/kolumn miejsc prowadzenia — pomiń.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $created = 0;

        Contractor::query()
            ->where('uses_business_locations', true)
            ->whereDoesntHave('locations')
            ->orderBy('id')
            ->chunkById(100, function ($contractors) use ($dryRun, &$created): void {
                foreach ($contractors as $contractor) {
                    $payload = [
                        'contractor_id' => $contractor->id,
                        'name' => 'Główny oddział',
                        'street' => $contractor->street,
                        'house_number' => $contractor->house_number,
                        'postal_code' => $contractor->postal_code,
                        'city' => $contractor->city,
                        'region' => $contractor->region,
                        'country' => $contractor->country,
                        'phone' => $contractor->phone,
                        'email' => $contractor->email,
                        'is_primary' => true,
                        'status' => 'active',
                    ];

                    if ($dryRun) {
                        $this->line("DRY: contractor #{$contractor->id} — {$contractor->name}");
                        $created++;

                        continue;
                    }

                    ContractorLocation::create($payload);
                    $created++;
                }
            });

        $this->info(($dryRun ? 'Do utworzenia: ' : 'Utworzono: ').$created.' miejsc prowadzenia.');

        return self::SUCCESS;
    }
}
