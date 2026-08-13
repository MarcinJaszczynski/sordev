<?php

namespace Tests\Feature;

use App\Models\EventTemplate;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateEventFromTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_event_from_template_saves_template_prices_snapshot()
    {
        // Załóżmy, że fabryki istnieją. Tworzymy place, template i kilka price rows.
        // Utwórz użytkownika testowego i ustaw jako zalogowany (events.created_by wymaga not null)
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        // Brak fabryki Place w repozytorium, tworzymy model bezpośrednio
        $place = Place::create(['name' => 'Test Place']);
        $template = EventTemplate::factory()->create(['name' => 'T1', 'start_place_id' => $place->id]);

        // Uruchom tworzenie eventu przez model
        $event = \App\Models\Event::createFromTemplate($template, [
            'name' => 'Event z szablonu',
            'client_name' => 'K',
            'start_date' => now()->format('Y-m-d'),
            'participant_count' => 10,
            'hotel_notes' => 'Pokój dla pilota od 8:00.',
        ]);

        $this->assertNotNull($event);
        $this->assertSame('Pokój dla pilota od 8:00.', $event->hotel_notes);
        $this->assertDatabaseHas('event_snapshots', [
            'event_id' => $event->id,
            'type' => 'original',
        ]);

        $snapshot = \App\Models\EventSnapshot::where('event_id', $event->id)->first();
        $this->assertNotNull($snapshot->template_prices_snapshot);

        // Impreza z szablonu ma od razu draft rozliczenia (wstępnie wypełniona ścieżka finansów).
        $settlement = \App\Models\EventSettlement::findActiveForEvent($event);
        $this->assertNotNull($settlement);
        $this->assertContains($settlement->status, ['draft', 'active', 'pilot_settled']);
    }
}
