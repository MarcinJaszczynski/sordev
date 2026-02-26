<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TempCreateEventSeeder extends Seeder
{
    public function run()
    {
        $t = \App\Models\EventTemplate::first();
        if (! $t) {
            $this->command->info("NO_TEMPLATE");
            return;
        }

        try {
            $e = \App\Models\Event::createFromTemplate($t, [
                'name' => 'TST_FROM_TEMPLATE',
                // supply minimal fields expected by createFromTemplate
                'client_name' => 'Seeder Test',
                'start_date' => now()->toDateString(),
                'participant_count' => 17,
            ]);
        } catch (\Exception $ex) {
            $this->command->error('EXCEPTION: ' . $ex->getMessage());
            $this->command->error($ex->getTraceAsString());
            return;
        }

        $count = \App\Models\EventProgramPoint::where('event_id', $e->id)->count();
        $this->command->info('EVENT_ID:' . $e->id);
        $this->command->info('PPP:' . $count);
    }
}
