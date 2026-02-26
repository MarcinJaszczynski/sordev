<?php

namespace App\Console\Commands;

use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PriceSummary extends Command
{
    protected $signature = 'prices:summary';
    protected $description = 'Pokaż podsumowanie cen: liczba szablonów, rekordów cen i ewentualne duplikaty.';

    public function handle(): int
    {
        try {
            $templates = EventTemplate::count();
            $prices = EventTemplatePricePerPerson::count();

            $dupeGroups = EventTemplatePricePerPerson::select('event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id')
                ->having('count', '>', 1)
                ->count();

            $this->info('Szablony: ' . $templates);
            $this->info('Rekordy cen: ' . $prices);
            $this->info('Grupy duplikatów: ' . $dupeGroups);

            // Dodatkowo: rozkład po walutach
            $byCurrency = DB::table('event_template_price_per_person')
                ->select('currency_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('currency_id')
                ->get();
            foreach ($byCurrency as $row) {
                $this->line('  - currency_id ' . $row->currency_id . ': ' . $row->cnt);
            }
            return 0;
        } catch (\Throwable $e) {
            $this->error('Błąd: ' . $e->getMessage());
            return 1;
        }
    }
}
