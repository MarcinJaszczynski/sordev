<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveDuplicateEventTemplatePrices extends Command
{
    protected $signature = 'event-templates:remove-duplicate-prices';
    protected $description = 'Usuwa duplikaty z event_template_price_per_person, zostawiając najnowszy rekord dla każdej kombinacji';

    public function handle()
    {
        $table = 'event_template_price_per_person';
        $sub = DB::table($table)
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id');

        $idsToKeep = $sub->pluck('id')->toArray();
        $deleted = DB::table($table)
            ->whereNotIn('id', $idsToKeep)
            ->delete();

        $this->info("Usunięto $deleted duplikatów z tabeli $table.");
        return 0;
    }
}
