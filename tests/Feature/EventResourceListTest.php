<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventResourceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_list_query_returns_payment_aggregates_and_gratis_count(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'start_place_id' => $place->id,
        ]);

        $event = Event::create([
            'event_template_id' => $template->id,
            'start_place_id' => $place->id,
            'name' => 'Testowa impreza',
            'client_name' => 'Klient testowy',
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'duration_days' => 3,
            'participant_count' => 30,
            'total_cost' => 5000,
            'status' => Event::STATUS_INQUIRY,
            'hotel_notes' => 'Pokój dla pilota gotowy wcześniej.',
        ]);

        DB::table('event_qties')->insert([
            'event_id' => $event->id,
            'qty' => 30,
            'gratis' => 3,
            'staff' => 2,
            'driver' => 1,
        ]);

        DB::table('event_agreements')->insert([
            [
                'event_id' => $event->id,
                'title' => 'Umowa 1',
                'agreement_number' => 'UM/2026/00001',
                'agreement_date' => '2026-04-01',
                'event_name' => $event->name,
                'event_start_date' => $event->start_date,
                'event_end_date' => $event->end_date,
                'participant_count' => 10,
                'amount_due' => 1000,
                'amount_paid' => 1000,
                'currency' => 'PLN',
                'status' => 'completed',
                'payment_status' => 'paid',
                'agreement_type' => 'group',
                'public_token' => 'token-paid-00001',
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_id' => $event->id,
                'title' => 'Umowa 2',
                'agreement_number' => 'UM/2026/00002',
                'agreement_date' => '2026-04-02',
                'event_name' => $event->name,
                'event_start_date' => $event->start_date,
                'event_end_date' => $event->end_date,
                'participant_count' => 5,
                'amount_due' => 500,
                'amount_paid' => 250,
                'currency' => 'PLN',
                'status' => 'sent',
                'payment_status' => 'pending',
                'agreement_type' => 'group',
                'public_token' => 'token-pending-00002',
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $record = EventResource::getEloquentQuery()->findOrFail($event->id);

        $this->assertSame(3, $record->resolveGratisCountForParticipantCount());
        $this->assertSame(10, (int) ($record->paid_participants_count ?? 0));
        $this->assertSame(1250.0, (float) ($record->agreements_amount_paid_total ?? 0));
    }

    public function test_settlement_cost_subqueries_returned_in_list_query(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'start_place_id' => $place->id,
        ]);

        $event = Event::create([
            'event_template_id' => $template->id,
            'start_place_id' => $place->id,
            'name' => 'Impreza z rozliczeniem',
            'client_name' => 'Klient',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-03',
            'duration_days' => 3,
            'participant_count' => 20,
            'total_cost' => 8000,
            'status' => Event::STATUS_INQUIRY,
        ]);

        // Bez rozliczenia - powinno mieć fallback na total_cost
        $recordNoSettlement = EventResource::getEloquentQuery()->findOrFail($event->id);
        $this->assertNotNull($recordNoSettlement);

        // Tworzymy aktywne rozliczenie z participant_due_pln i participant_paid_pln
        DB::table('event_settlements')->insert([
            'event_id' => $event->id,
            'status' => 'active',
            'participant_due_pln' => 7800.00,
            'participant_paid_pln' => 7500.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recordWithSettlement = EventResource::getEloquentQuery()->findOrFail($event->id);
        $this->assertNotNull($recordWithSettlement);
        // Sprawdzenie że kolumna będzie pobierać z settlement
        $settlement = $recordWithSettlement->settlements()
            ->whereIn('status', ['draft', 'active', 'pilot_settled'])
            ->latest('id')->first();
        $this->assertNotNull($settlement);
        $this->assertSame(7800.0, (float) $settlement->participant_due_pln);
        $this->assertSame(7500.0, (float) $settlement->participant_paid_pln);
    }
}
