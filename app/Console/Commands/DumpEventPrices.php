<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DumpEventPrices extends Command
{
    protected $signature = 'debug:dump-event-prices {eventId}';
    protected $description = 'Dump event_price_per_person rows for an event';

    public function handle()
    {
        $id = $this->argument('eventId');
        $rows = \App\Models\EventPricePerPerson::where('event_id', $id)->get();
        if ($rows->isEmpty()) {
            $this->info('No rows');
            return 0;
        }

        foreach ($rows as $r) {
            $this->line(json_encode($r->toArray(), JSON_UNESCAPED_UNICODE));
        }

        return 0;
    }
}
