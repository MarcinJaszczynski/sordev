<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DumpEventProgramPoints extends Command
{
    protected $signature = 'debug:dump-event-points {eventId}';
    protected $description = 'Dump program points for event as JSON lines';

    public function handle()
    {
        $id = $this->argument('eventId');
        $event = \App\Models\Event::find($id);
        if (!$event) {
            $this->error('Event not found');
            return 1;
        }

        foreach ($event->programPoints()->get() as $p) {
            $this->line(json_encode([
                'id' => $p->id,
                'template_id' => $p->event_template_program_point_id,
                'name' => $p->name,
                'parent_id' => $p->parent_id,
                'unit_price' => $p->unit_price,
                'currency_id' => $p->currency_id,
                'featured_image' => $p->featured_image,
                'gallery_images' => $p->gallery_images,
            ], JSON_UNESCAPED_UNICODE));
        }

        return 0;
    }
}
