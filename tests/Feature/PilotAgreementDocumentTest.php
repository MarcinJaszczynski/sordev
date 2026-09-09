<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContractorSettlementForm;
use App\Mail\PilotCivilContractMail;
use App\Models\Contractor;
use App\Models\ContractorType;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventPilotAgreement;
use App\Models\User;
use App\Services\PilotAgreementDocumentService;
use App\Services\PilotFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PilotAgreementDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('event_pilot_agreements')) {
            $this->markTestSkipped('event_pilot_agreements table is required.');
        }

        Storage::fake('public');
        Mail::fake();
    }

    public function test_generate_creates_pdf_for_contractor_only_pilot(): void
    {
        [$event, $contractor] = $this->makeEventWithPilotContractor();

        $agreement = app(PilotAgreementDocumentService::class)->generate($event);

        $this->assertInstanceOf(EventPilotAgreement::class, $agreement);
        $this->assertSame($contractor->id, $agreement->contractor_id);
        $this->assertTrue($agreement->hasPdf());
        $this->assertStringContainsString('PILOT/', (string) $agreement->contract_number);
        $this->assertStringContainsString($contractor->name, (string) $agreement->body_html);
        Storage::disk('public')->assertExists($agreement->pdf_path);
    }

    public function test_send_email_goes_to_contractor_without_user(): void
    {
        [$event] = $this->makeEventWithPilotContractor();

        $service = app(PilotAgreementDocumentService::class);
        $service->generate($event);
        $service->sendEmail($event);

        Mail::assertSent(PilotCivilContractMail::class, function (PilotCivilContractMail $mail) use ($event): bool {
            return $mail->event->is($event)
                && $mail->hasTo('pilot.only@example.test');
        });

        $this->assertNotNull($service->currentForEvent($event)?->sent_at);
    }

    public function test_share_in_portal_creates_event_document_with_pilot_flag(): void
    {
        [$event] = $this->makeEventWithPilotContractor();

        $service = app(PilotAgreementDocumentService::class);
        $service->generate($event);
        $agreement = $service->shareInPortal($event, true);

        $this->assertTrue($agreement->shared_in_portal);
        $this->assertNotNull($agreement->event_document_id);

        $document = EventDocument::query()->find($agreement->event_document_id);
        $this->assertNotNull($document);
        $this->assertTrue((bool) $document->attach_to_pilot_pdf);
        Storage::disk('public')->assertExists($document->file_path);
    }

    public function test_generate_requires_contractor(): void
    {
        $event = Event::factory()->create([
            'assigned_to' => User::factory()->create()->id,
            'pilot_contractor_id' => null,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(PilotAgreementDocumentService::class)->generate($event);
    }

    /**
     * @return array{0: Event, 1: Contractor}
     */
    protected function makeEventWithPilotContractor(): array
    {
        $pilotType = ContractorType::query()->firstOrCreate(['name' => 'pilot']);
        ContractorType::clearIdsForNamesCache();

        $contractor = Contractor::create([
            'name' => 'Anna Pilot',
            'email' => 'pilot.only@example.test',
            'phone' => '+48111222333',
            'pesel' => '90010112345',
            'bank_account' => '12 3456 7890 1234 5678 9012 3456',
            'settlement_form' => ContractorSettlementForm::ContractOfWork,
            'status' => 'active',
        ]);
        $contractor->types()->sync([$pilotType->getKey()]);

        $event = Event::factory()->create([
            'name' => 'Wycieczka testowa',
            'code' => 'TEST-PILOT-1',
            'assigned_to' => null,
            'pilot_contractor_id' => $contractor->id,
            'pilot_settlement_form' => null,
        ]);

        if (Schema::hasTable('pilot_fee_lines')) {
            $pln = Currency::query()->create([
                'name' => 'PLN',
                'code' => 'PLN',
                'symbol' => 'PLN',
                'exchange_rate' => 1,
            ]);

            app(PilotFeeService::class)->syncDueLines($event, [
                ['amount' => 1200, 'currency_id' => $pln->id],
            ]);
        }

        return [$event->fresh(), $contractor];
    }
}
