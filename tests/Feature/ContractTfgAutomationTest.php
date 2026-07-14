<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\EventType;
use App\Models\Place;
use App\Models\TransportType;
use App\Models\User;
use App\Services\ContractTfgSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractTfgAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_from_event_pull_participant_count_and_dates(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Wycieczka szkolna',
            'client_name' => 'Szkoła Testowa',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-05',
            'participant_count' => 42,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $defaults = app(ContractTfgSetupService::class)->defaultsFromEvent($event);

        $this->assertSame(42, $defaults['tfg_travelers_count']);
        $this->assertSame('2026-07-01', $defaults['tfg_starts_at']);
        $this->assertSame('2026-07-05', $defaults['tfg_ends_at']);
        $this->assertSame(sprintf('IMP-%05d', $event->id), $defaults['reservation_number']);
    }

    public function test_defaults_from_event_infer_flight_transport_for_plane_template(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();
        $plane = TransportType::create(['name' => 'Samolot']);

        $template->transportTypes()->attach($plane->id);

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Wycieczka lotnicza',
            'client_name' => 'Szkoła Testowa',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-07',
            'participant_count' => 20,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $defaults = app(ContractTfgSetupService::class)->defaultsFromEvent($event->fresh());

        $this->assertSame('LOTNCZART', $defaults['tfg_transport_code']);
    }

    public function test_defaults_from_event_use_foreign_scope_for_zagraniczne_template(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();
        $foreignType = EventType::create(['name' => 'zagraniczne']);
        $destination = Place::create(['name' => 'Rzym', 'starting_place' => false]);

        $template->eventTypes()->attach($foreignType->id);
        $template->update(['end_place_id' => $destination->id]);

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Włochy',
            'client_name' => 'Szkoła Testowa',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-08',
            'participant_count' => 30,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $defaults = app(ContractTfgSetupService::class)->defaultsFromEvent($event->fresh());

        $this->assertSame('EUR', $defaults['tfg_scope_type']);
        $this->assertSame('Rzym', $defaults['tfg_locality']);
    }

    public function test_apply_to_contract_syncs_main_fields_from_tfg_form_data(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza sync',
            'client_name' => 'Szkoła Testowa',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-05',
            'participant_count' => 10,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa',
            'contract_date' => now()->toDateString(),
            'participant_count' => 10,
            'event_start_date' => '2026-07-01',
            'event_end_date' => '2026-07-05',
            'total_price' => 5000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        app(ContractTfgSetupService::class)->applyToContract($contract, [
            'subject_code' => 'IT',
            'payment_method_code' => 'WPLATAPRZED',
            'tfg_travelers_count' => 25,
            'tfg_starts_at' => '2026-08-01',
            'tfg_ends_at' => '2026-08-10',
            'tfg_scope_type' => 'PLISAS',
            'tfg_country_code' => 'PL',
            'tfg_transport_code' => 'NLOT',
        ]);

        $contract->refresh();
        $variant = $contract->variants()->first();

        $this->assertSame(25, $contract->participant_count);
        $this->assertSame('2026-08-01', optional($contract->event_start_date)->toDateString());
        $this->assertSame('2026-08-10', optional($contract->event_end_date)->toDateString());
        $this->assertSame(25, $variant?->travelers_count);
        $this->assertSame('2026-08-01', optional($variant?->starts_at)->toDateString());
    }
}
