<?php

namespace Tests\Feature;

use App\Actions\Finance\CreateClientInvoiceRequestAction;
use App\Mail\ClientInvoiceRequestAdminMail;
use App\Mail\ClientInvoiceRequestConfirmationMail;
use App\Models\ClientInvoiceRequest;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use App\Services\ClientInvoiceRequestWorkflowService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicInvoiceRequestTest extends TestCase
{
    use RefreshDatabase;

    private string $regionSlug = 'warszawa';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'ksiegowosc', 'biuro', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // /region/* jest globalnie 301 → kanoniczny slug (np. warszawa).
        Place::factory()->create([
            'name' => 'Warszawa',
            'slug' => 'warszawa',
            'starting_place' => true,
        ]);
        URL::defaults(['regionSlug' => $this->regionSlug]);
    }

    public function test_public_form_creates_request_and_sends_mails(): void
    {
        if (! Schema::hasTable('client_invoice_requests')) {
            $this->markTestSkipped('Brak tabeli client_invoice_requests.');
        }

        Mail::fake();

        $event = Event::factory()->create(['code' => '26TEST01']);

        $response = $this->from(route('invoice-request', ['regionSlug' => $this->regionSlug]))
            ->post(route('invoice-request.submit', ['regionSlug' => $this->regionSlug]), [
                'buyer_type' => ClientInvoiceRequest::BUYER_COMPANY,
                'company_name' => 'Szkoła Testowa',
                'nip' => '5250000000',
                'street' => 'ul. Test',
                'house_number' => '1',
                'postal_code' => '00-001',
                'city' => 'Warszawa',
                'invoice_email' => 'wnioskodawca@example.com',
                'applicant_phone' => '500100200',
                'event_code' => '26TEST01',
                'amount' => 1500,
                'payment_reference' => 'REF-1',
                'notes' => 'Proszę o FV',
                'website' => '',
                'form_ts' => (string) (int) ((microtime(true) * 1000) - 5000),
            ]);

        $response->assertRedirect(route('invoice-request', ['regionSlug' => $this->regionSlug]));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('client_invoice_requests', [
            'event_id' => $event->id,
            'company_name' => 'Szkoła Testowa',
            'buyer_type' => ClientInvoiceRequest::BUYER_COMPANY,
            'source' => ClientInvoiceRequest::SOURCE_WEB,
            'status' => ClientInvoiceRequest::STATUS_PENDING,
            'user_id' => null,
        ]);

        Mail::assertSent(ClientInvoiceRequestConfirmationMail::class);
        Mail::assertSent(ClientInvoiceRequestAdminMail::class);
    }

    public function test_public_form_rejects_unknown_event_code(): void
    {
        if (! Schema::hasTable('client_invoice_requests')) {
            $this->markTestSkipped('Brak tabeli client_invoice_requests.');
        }

        Mail::fake();

        $response = $this->from(route('invoice-request', ['regionSlug' => $this->regionSlug]))
            ->post(route('invoice-request.submit', ['regionSlug' => $this->regionSlug]), [
                'buyer_type' => ClientInvoiceRequest::BUYER_PERSON,
                'company_name' => 'Jan Nowak',
                'invoice_email' => 'jan@example.com',
                'event_code' => '99NOEXIST',
                'website' => '',
                'form_ts' => (string) (int) ((microtime(true) * 1000) - 5000),
            ]);

        $response->assertRedirect(route('invoice-request', ['regionSlug' => $this->regionSlug]));
        $response->assertSessionHasErrors('event_code');
        $this->assertDatabaseCount('client_invoice_requests', 0);
        Mail::assertNothingSent();
    }

    public function test_check_code_endpoint_validates_event(): void
    {
        if (! Schema::hasTable('events')) {
            $this->markTestSkipped('Brak tabeli events.');
        }

        $event = Event::factory()->create(['code' => '26ABCD12']);

        $ok = $this->postJson(route('invoice-request.check-code', ['regionSlug' => $this->regionSlug]), [
            'event_code' => '26abcd12',
        ]);
        $ok->assertOk()->assertJson(['valid' => true, 'code' => '26ABCD12']);

        $bad = $this->postJson(route('invoice-request.check-code', ['regionSlug' => $this->regionSlug]), [
            'event_code' => 'NOPE0000',
        ]);
        $bad->assertOk()->assertJson(['valid' => false]);
        $this->assertSame($event->id, Event::query()->where('code', '26ABCD12')->value('id'));
    }

    public function test_inbox_can_attach_event_to_unlinked_request(): void
    {
        if (! Schema::hasTable('client_invoice_requests')) {
            $this->markTestSkipped('Brak tabeli client_invoice_requests.');
        }

        Mail::fake();

        $event = Event::factory()->create(['code' => '26LINK01']);
        $request = ClientInvoiceRequest::query()->create([
            'event_id' => null,
            'event_code_entered' => '26WRONG1',
            'user_id' => null,
            'buyer_type' => ClientInvoiceRequest::BUYER_COMPANY,
            'source' => ClientInvoiceRequest::SOURCE_WEB,
            'company_name' => 'Do powiązania',
            'nip' => '1111111111',
            'invoice_email' => 'link@example.com',
            'status' => ClientInvoiceRequest::STATUS_PENDING,
        ]);

        $user = User::factory()->create();
        $user->assignRole('biuro');

        Filament::setServingStatus(true);
        $this->actingAs($user);

        Livewire::test(\App\Filament\Pages\ClientInvoiceRequestsInboxPage::class)
            ->callTableAction('attachEvent', $request, data: ['event_id' => $event->id])
            ->assertHasNoTableActionErrors();

        $request->refresh();
        $this->assertSame($event->id, $request->event_id);

        app(ClientInvoiceRequestWorkflowService::class)->markProcessed($request, $user, 'OK');
        $this->assertSame(ClientInvoiceRequest::STATUS_PROCESSED, $request->fresh()->status);
    }

    public function test_person_buyer_does_not_require_nip(): void
    {
        if (! Schema::hasTable('client_invoice_requests')) {
            $this->markTestSkipped('Brak tabeli client_invoice_requests.');
        }

        Mail::fake();

        $event = Event::factory()->create(['code' => '26PERS01']);

        $this->from(route('invoice-request', ['regionSlug' => $this->regionSlug]))
            ->post(route('invoice-request.submit', ['regionSlug' => $this->regionSlug]), [
                'buyer_type' => ClientInvoiceRequest::BUYER_PERSON,
                'company_name' => 'Ewa Demo',
                'invoice_email' => 'ewa@example.com',
                'event_code' => '26PERS01',
                'website' => '',
                'form_ts' => (string) (int) ((microtime(true) * 1000) - 5000),
            ])->assertSessionHas('success');

        $this->assertDatabaseHas('client_invoice_requests', [
            'event_id' => $event->id,
            'buyer_type' => ClientInvoiceRequest::BUYER_PERSON,
            'company_name' => 'Ewa Demo',
            'nip' => null,
        ]);
    }

    public function test_action_sends_confirmation_mail(): void
    {
        if (! Schema::hasTable('client_invoice_requests')) {
            $this->markTestSkipped('Brak tabeli client_invoice_requests.');
        }

        Mail::fake();
        $event = Event::factory()->create();

        $request = app(CreateClientInvoiceRequestAction::class)(new \App\Data\CreateClientInvoiceRequestData(
            buyerType: ClientInvoiceRequest::BUYER_COMPANY,
            companyName: 'Action Co',
            invoiceEmail: 'action@example.com',
            source: ClientInvoiceRequest::SOURCE_WEB,
            nip: '2222222222',
            event: $event,
            eventCodeEntered: $event->code,
        ));

        $this->assertNotNull($request->id);
        Mail::assertSent(ClientInvoiceRequestConfirmationMail::class, function (ClientInvoiceRequestConfirmationMail $mail) use ($request) {
            return $mail->invoiceRequest->is($request);
        });
    }
}
