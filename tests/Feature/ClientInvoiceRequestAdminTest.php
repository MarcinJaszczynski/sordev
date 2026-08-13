<?php

namespace Tests\Feature;

use App\Filament\Pages\ClientInvoiceRequestsInboxPage;
use App\Models\ClientInvoiceRequest;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use App\Services\ClientInvoiceRequestWorkflowService;
use App\Services\NotificationService;
use App\Support\ClientInvoiceRequestAdminHelper;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientInvoiceRequestAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'ksiegowosc', 'biuro', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_finance_user_can_access_invoice_requests_inbox(): void
    {
        if (! Schema::hasTable('client_invoice_requests')) {
            $this->markTestSkipped('Brak tabeli client_invoice_requests.');
        }

        $user = User::factory()->create();
        $user->assignRole('ksiegowosc');

        Filament::setServingStatus(true);
        $this->actingAs($user);

        $this->assertTrue(ClientInvoiceRequestsInboxPage::canAccess());
    }

    public function test_workflow_marks_request_processed(): void
    {
        if (! Schema::hasTable('client_invoice_requests')) {
            $this->markTestSkipped('Brak tabeli client_invoice_requests.');
        }

        $actor = User::factory()->create();
        $request = $this->createPendingRequest();

        app(ClientInvoiceRequestWorkflowService::class)->markProcessed($request, $actor, 'Wystawiono FV');

        $request->refresh();
        $this->assertSame(ClientInvoiceRequest::STATUS_PROCESSED, $request->status);
        $this->assertSame($actor->id, $request->processed_by);
        $this->assertNotNull($request->processed_at);
        $this->assertSame('Wystawiono FV', $request->admin_notes);

        if (Schema::hasTable('sales_invoices')) {
            $this->assertDatabaseHas('sales_invoices', [
                'event_id' => $request->event_id,
                'client_invoice_request_id' => $request->id,
                'procedure' => 'vat_margin',
                'status' => 'draft',
            ]);
        }
    }

    public function test_workflow_rejects_request_with_note(): void
    {
        if (! Schema::hasTable('client_invoice_requests')) {
            $this->markTestSkipped('Brak tabeli client_invoice_requests.');
        }

        $actor = User::factory()->create();
        $request = $this->createPendingRequest();

        app(ClientInvoiceRequestWorkflowService::class)->markRejected($request, $actor, 'Brak NIP na białej liście');

        $request->refresh();
        $this->assertSame(ClientInvoiceRequest::STATUS_REJECTED, $request->status);
        $this->assertSame('Brak NIP na białej liście', $request->admin_notes);
    }

    public function test_topbar_invoice_notification_links_to_inbox(): void
    {
        if (! Schema::hasTable('client_invoice_requests')) {
            $this->markTestSkipped('Brak tabeli client_invoice_requests.');
        }

        $user = User::factory()->create();
        $user->assignRole('ksiegowosc');

        $this->createPendingRequest();

        $data = NotificationService::getTopbarDataForUser(
            $user->id,
            NotificationService::TOPBAR_LIMIT_PER_TYPE,
            NotificationService::TOPBAR_COMBINED_LIMIT,
            NotificationService::TOPBAR_TASK_QUERY_LIMIT,
            fresh: true,
        );

        $this->assertNotEmpty($data['items_by_type']['invoice_request']);
        $this->assertStringContainsString(
            'client-invoice-requests',
            (string) $data['items_by_type']['invoice_request'][0]['url'],
        );
    }

    public function test_prefill_from_participant_uses_contact_and_payment_data(): void
    {
        if (! Schema::hasTable('client_invoice_requests') || ! Schema::hasTable('event_participants')) {
            $this->markTestSkipped('Brak wymaganych tabel.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
        ]);
        $payment = EventSettlementParticipantPayment::create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Jan Kowalski',
            'due_amount_pln' => 1200,
            'paid_amount_pln' => 600,
        ]);
        $participant = EventParticipant::create([
            'event_id' => $event->id,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.com',
            'phone' => '500600700',
            'booking_reference' => 'REF-123',
            'participant_payment_id' => $payment->id,
            'source' => EventParticipant::SOURCE_MANUAL,
            'status' => EventParticipant::STATUS_ACTIVE,
        ]);

        $prefill = ClientInvoiceRequestAdminHelper::prefillFromParticipant($participant);

        $this->assertSame($event->id, $prefill['event_id']);
        $this->assertSame(ClientInvoiceRequest::BUYER_PERSON, $prefill['buyer_type']);
        $this->assertSame('Jan Kowalski', $prefill['company_name']);
        $this->assertSame('jan@example.com', $prefill['invoice_email']);
        $this->assertSame('500600700', $prefill['applicant_phone']);
        $this->assertSame('REF-123', $prefill['payment_reference']);
        $this->assertSame(600.0, $prefill['amount']);
    }

    public function test_admin_form_creates_request_in_inbox(): void
    {
        if (! Schema::hasTable('client_invoice_requests')) {
            $this->markTestSkipped('Brak tabeli client_invoice_requests.');
        }

        $event = Event::factory()->create(['code' => 'EVT-99']);

        $request = ClientInvoiceRequestAdminHelper::createFromAdminForm([
            'event_id' => $event->id,
            'buyer_type' => ClientInvoiceRequest::BUYER_PERSON,
            'company_name' => 'Jan Test',
            'invoice_email' => 'jan@test.local',
            'amount' => 100,
            'payment_reference' => 'UM-1',
        ]);

        $this->assertDatabaseHas('client_invoice_requests', [
            'id' => $request->id,
            'event_id' => $event->id,
            'source' => ClientInvoiceRequest::SOURCE_ADMIN,
            'status' => ClientInvoiceRequest::STATUS_PENDING,
            'company_name' => 'Jan Test',
        ]);
    }

    public function test_inbox_page_can_mark_request_processed(): void
    {
        if (! Schema::hasTable('client_invoice_requests')) {
            $this->markTestSkipped('Brak tabeli client_invoice_requests.');
        }

        $user = User::factory()->create();
        $user->assignRole('admin');
        $request = $this->createPendingRequest();

        Filament::setServingStatus(true);
        $this->actingAs($user);

        Livewire::test(ClientInvoiceRequestsInboxPage::class)
            ->callTableAction('markProcessed', $request, data: ['admin_notes' => 'OK'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('client_invoice_requests', [
            'id' => $request->id,
            'status' => ClientInvoiceRequest::STATUS_PROCESSED,
        ]);
    }

    private function createPendingRequest(): ClientInvoiceRequest
    {
        $event = Event::factory()->create();
        $client = User::factory()->create();

        return ClientInvoiceRequest::create([
            'event_id' => $event->id,
            'user_id' => $client->id,
            'company_name' => 'Test Sp. z o.o.',
            'nip' => '1234567890',
            'invoice_email' => 'faktury@test.local',
            'status' => ClientInvoiceRequest::STATUS_PENDING,
        ]);
    }
}
