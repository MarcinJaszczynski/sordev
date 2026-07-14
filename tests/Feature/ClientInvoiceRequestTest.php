<?php

namespace Tests\Feature;

use App\Filament\Client\Pages\ClientInvoiceRequestPage;
use App\Models\ClientInvoiceRequest;
use App\Models\Contract;
use App\Models\Event;
use App\Models\EventPortalAccess;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientInvoiceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'client_guardian']);
        Role::firstOrCreate(['name' => 'client_participant']);
    }

    public function test_guardian_can_submit_invoice_request(): void
    {
        if (! Schema::hasTable('client_invoice_requests') || ! Schema::hasTable('contracts')) {
            $this->markTestSkipped('Brak tabel client_invoice_requests lub contracts.');
        }

        $event = Event::factory()->create();
        $guardian = User::factory()->create(['status' => 'active', 'email' => 'guardian@test.local']);
        $guardian->assignRole('client_guardian');

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa grupowa',
            'contract_date' => now()->toDateString(),
            'customer_name' => 'Szkoła',
            'signer_email' => 'guardian@test.local',
            'total_price' => 3000,
            'currency' => 'PLN',
            'status' => 'signed',
            'payment_status' => 'pending',
        ]);

        EventPortalAccess::create([
            'event_id' => $event->id,
            'user_id' => $guardian->id,
            'role' => EventPortalAccess::ROLE_GUARDIAN,
            'contract_id' => $contract->id,
            'shared_at' => now(),
            'source' => EventPortalAccess::SOURCE_ADMIN,
        ]);

        Filament::setServingStatus(true);
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($guardian);

        Livewire::test(ClientInvoiceRequestPage::class, ['event' => $event])
            ->fillForm([
                'company_name' => 'Szkoła Podstawowa nr 1',
                'nip' => '1234567890',
                'invoice_email' => 'faktury@szkola.pl',
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('client_invoice_requests', [
            'event_id' => $event->id,
            'user_id' => $guardian->id,
            'company_name' => 'Szkoła Podstawowa nr 1',
            'status' => ClientInvoiceRequest::STATUS_PENDING,
        ]);

        Filament::setServingStatus(false);
        Filament::setCurrentPanel(null);
    }

    public function test_participant_can_submit_invoice_request(): void
    {
        if (! Schema::hasTable('client_invoice_requests') || ! Schema::hasTable('contracts')) {
            $this->markTestSkipped('Brak tabel client_invoice_requests lub contracts.');
        }

        $event = Event::factory()->create();
        $participant = User::factory()->create(['status' => 'active', 'email' => 'participant@test.local']);
        $participant->assignRole('client_participant');

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa indywidualna',
            'contract_date' => now()->toDateString(),
            'participant_name' => 'Anna Test',
            'signer_email' => 'participant@test.local',
            'total_price' => 1500,
            'currency' => 'PLN',
            'status' => 'signed',
            'payment_status' => 'pending',
        ]);

        EventPortalAccess::create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'role' => EventPortalAccess::ROLE_PARTICIPANT,
            'contract_id' => $contract->id,
            'shared_at' => now(),
            'source' => EventPortalAccess::SOURCE_ADMIN,
        ]);

        Filament::setServingStatus(true);
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($participant);

        Livewire::test(ClientInvoiceRequestPage::class, ['event' => $event])
            ->fillForm([
                'company_name' => 'Anna Test',
                'nip' => '9876543210',
                'invoice_email' => 'participant@test.local',
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('client_invoice_requests', [
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'contract_id' => $contract->id,
            'company_name' => 'Anna Test',
            'status' => ClientInvoiceRequest::STATUS_PENDING,
        ]);

        Filament::setServingStatus(false);
        Filament::setCurrentPanel(null);
    }
}
