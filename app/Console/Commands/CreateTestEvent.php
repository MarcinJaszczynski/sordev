<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EventTemplate;
use App\Services\EventPriceCalculator;

class CreateTestEvent extends Command
{
    protected $signature = 'create:test-event {templateId=1}';
    protected $description = 'Create a test event from template and run per-event calculation';

    public function handle()
    {
        $templateId = (int)$this->argument('templateId');
        $t = EventTemplate::find($templateId);
        if (!$t) {
            $this->error('Template not found');
            return 1;
        }

        $e = \App\Models\Event::createFromTemplate($t, [
            'name' => 'Test from template CLI',
            'client_name' => 'CLI Tester',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'participant_count' => 10,
        ]);

        $this->info('Event created: ' . $e->id);

        $calc = new EventPriceCalculator();
        $calc->calculateForEvent($e);

        $count = \App\Models\EventPricePerPerson::where('event_id', $e->id)->count();
        $this->info('Prices count: ' . $count);

        return 0;
    }
}
