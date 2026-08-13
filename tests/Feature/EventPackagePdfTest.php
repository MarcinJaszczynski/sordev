<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventPackagePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_agreements_relationship_orders_by_contract_date_on_contracts_table(): void
    {
        if (! Schema::hasTable('contracts')) {
            $this->markTestSkipped('Tabela contracts nie istnieje w tym środowisku testowym.');
        }

        $event = $this->createEventWithContracts();

        $agreements = $event->fresh()->agreements()->get();

        $this->assertCount(2, $agreements);
        $this->assertTrue($agreements->first()->contract_date->gte($agreements->last()->contract_date));
    }

    public function test_pilot_package_pdf_downloads_when_contracts_table_exists(): void
    {
        if (! Schema::hasTable('contracts')) {
            $this->markTestSkipped('Tabela contracts nie istnieje w tym środowisku testowym.');
        }

        $event = $this->createEventWithContracts();

        $response = $this->get(route('admin.events.pdf', [
            'event' => $event->id,
            'audience' => 'pilot',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    private function createEventWithContracts(): Event
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $template = EventTemplate::factory()->create();
        $contractTemplate = ContractTemplate::create([
            'name' => 'Szablon testowy',
            'content' => 'Treść umowy testowej',
        ]);

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza PDF',
            'client_name' => 'Klient Test',
            'client_email' => 'klient@test.com',
            'client_phone' => '500600700',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 20,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        Contract::create([
            'event_id' => $event->id,
            'contract_template_id' => $contractTemplate->id,
            'agreement_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa starsza',
            'agreement_date' => now()->subDays(5)->toDateString(),
            'event_name' => $event->name,
            'event_start_date' => $event->start_date,
            'event_end_date' => $event->end_date,
            'customer_name' => $event->client_name,
            'participant_count' => 20,
            'amount_due' => 1000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        Contract::create([
            'event_id' => $event->id,
            'contract_template_id' => $contractTemplate->id,
            'agreement_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa nowsza',
            'agreement_date' => now()->toDateString(),
            'event_name' => $event->name,
            'event_start_date' => $event->start_date,
            'event_end_date' => $event->end_date,
            'customer_name' => $event->client_name,
            'participant_count' => 20,
            'amount_due' => 1000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        return $event;
    }
}
