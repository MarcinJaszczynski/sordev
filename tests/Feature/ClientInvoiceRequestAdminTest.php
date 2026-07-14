<?php

namespace Tests\Feature;

use App\Filament\Pages\ClientInvoiceRequestsInboxPage;
use App\Models\ClientInvoiceRequest;
use App\Models\Event;
use App\Models\User;
use App\Services\ClientInvoiceRequestWorkflowService;
use App\Services\NotificationService;
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
