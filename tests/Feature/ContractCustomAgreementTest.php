<?php

namespace Tests\Feature;

use App\Http\Controllers\Front\AgreementFlowController;
use App\Models\Contract;
use App\Models\Event;
use App\Models\EventAgreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

class ContractCustomAgreementTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_upload_contract_does_not_auto_generate_agreement_body(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_CUSTOM,
            'body_edit_mode' => Contract::BODY_EDIT_UPLOAD,
            'custom_agreement_document_path' => 'event-agreements/custom-documents/sample.pdf',
            'title' => 'Umowa własna',
            'contract_date' => now()->toDateString(),
            'event_name' => $event->name,
            'participant_count' => 1,
            'total_price' => 1000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $contract->refresh();

        $this->assertTrue($contract->isCustom());
        $this->assertTrue($contract->usesUploadedAgreementDocument());
        $this->assertFalse($contract->shouldAutoGenerateAgreementBody());
        $this->assertNull($contract->agreement_body);
    }

    public function test_regenerate_agreement_body_is_skipped_for_uploaded_custom_document(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_CUSTOM,
            'body_edit_mode' => Contract::BODY_EDIT_UPLOAD,
            'custom_agreement_document_path' => 'event-agreements/custom-documents/sample.pdf',
            'agreement_body' => null,
            'title' => 'Umowa własna',
            'contract_date' => now()->toDateString(),
            'event_name' => $event->name,
            'participant_count' => 1,
            'total_price' => 1000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $contract->regenerateAgreementBody();
        $contract->refresh();

        $this->assertNull($contract->agreement_body);
    }

    public function test_render_agreement_pdf_uses_uploaded_document_bytes(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(
            'event-agreements/custom-documents/sample.pdf',
            '%PDF-1.4 custom agreement content',
        );

        $user = User::factory()->create();
        $event = Event::factory()->create();

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_CUSTOM,
            'body_edit_mode' => Contract::BODY_EDIT_UPLOAD,
            'custom_agreement_document_path' => 'event-agreements/custom-documents/sample.pdf',
            'title' => 'Umowa własna',
            'contract_date' => now()->toDateString(),
            'event_name' => $event->name,
            'participant_count' => 1,
            'total_price' => 1000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $controller = app(AgreementFlowController::class);
        $method = new ReflectionMethod($controller, 'renderAgreementPdf');
        $method->setAccessible(true);

        $pdfBytes = $method->invoke($controller, $contract->fresh());

        $this->assertSame('%PDF-1.4 custom agreement content', $pdfBytes);
    }

    public function test_legacy_event_agreement_supports_uploaded_custom_document(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $agreement = EventAgreement::create([
            'event_id' => $event->id,
            'agreement_type' => EventAgreement::TYPE_CUSTOM,
            'body_edit_mode' => EventAgreement::BODY_EDIT_UPLOAD,
            'custom_agreement_document_path' => 'event-agreements/custom-documents/legacy.pdf',
            'title' => 'Umowa legacy',
            'agreement_date' => now()->toDateString(),
            'event_name' => $event->name,
            'participant_count' => 1,
            'amount_due' => 1000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $agreement->refresh();

        $this->assertTrue($agreement->usesUploadedAgreementDocument());
        $this->assertFalse($agreement->shouldAutoGenerateAgreementBody());
        $this->assertNull($agreement->agreement_body);
    }
}
