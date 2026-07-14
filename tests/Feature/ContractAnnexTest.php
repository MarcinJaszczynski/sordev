<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplate;
use App\Models\User;
use App\Services\ContractAnnexService;
use App\Services\ContractTfgSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractAnnexTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_annex_with_program_change_stores_snapshot(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza program',
            'client_name' => 'Szkoła',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'participant_count' => 12,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Zwiedzanie muzeum',
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'active' => true,
        ]);

        $parent = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa bazowa',
            'contract_date' => now()->toDateString(),
            'total_price' => 3000,
            'currency' => 'PLN',
            'status' => 'signed',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $annex = app(ContractTfgSetupService::class)->createAnnex($parent, [
            'title' => 'Aneks program',
            'agreement_date' => now()->toDateString(),
            'amount_due' => 3000,
            'annex_change_types' => [Contract::ANNEX_CHANGE_PROGRAM],
            'annex_program_change_notes' => 'Dodano punkt programu.',
            'body_edit_mode' => Contract::BODY_EDIT_MANUAL,
            'agreement_body' => '<p>Treść aneksu ręczna</p>',
        ]);

        $annex->refresh();

        $this->assertTrue($annex->isAnnex());
        $this->assertTrue($annex->hasAnnexProgramChange());
        $this->assertSame('Dodano punkt programu.', $annex->annex_program_change_notes);
        $this->assertSame('<p>Treść aneksu ręczna</p>', $annex->agreement_body);
        $this->assertCount(1, $annex->annex_program_snapshot['points'] ?? []);
        $this->assertSame('Zwiedzanie muzeum', $annex->annex_program_snapshot['points'][0]['name']);
    }

    public function test_manual_annex_does_not_auto_generate_body_from_template(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza',
            'client_name' => 'Szkoła',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 5,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $parent = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa',
            'contract_date' => now()->toDateString(),
            'total_price' => 1000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $annex = app(ContractTfgSetupService::class)->createAnnex($parent, [
            'title' => 'Aneks ręczny',
            'agreement_date' => now()->toDateString(),
            'amount_due' => 1000,
            'annex_change_types' => [Contract::ANNEX_CHANGE_PRICE],
            'body_edit_mode' => Contract::BODY_EDIT_MANUAL,
            'agreement_body' => 'Własna treść aneksu',
        ]);

        $this->assertFalse($annex->shouldAutoGenerateAgreementBody());
        $this->assertSame('Własna treść aneksu', $annex->agreement_body);
    }

    public function test_annex_template_payload_includes_program_and_change_labels(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['client_name' => 'Szkoła']);

        $parent = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa',
            'contract_date' => now()->toDateString(),
            'contract_number' => 'UM/2026/00001',
            'total_price' => 1000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $annex = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Aneks',
            'contract_date' => now()->toDateString(),
            'contract_number' => 'UM/2026/00002',
            'total_price' => 1000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'annex_change_types' => [Contract::ANNEX_CHANGE_PROGRAM, Contract::ANNEX_CHANGE_PRICE],
            'annex_program_change_notes' => 'Nowy punkt',
            'annex_program_snapshot' => [
                'points' => [
                    ['day' => 1, 'name' => 'Kino', 'start_time' => '10:00', 'children' => []],
                ],
            ],
            'meta' => ['is_annex' => true, 'parent_contract_id' => $parent->id],
            'created_by' => $user->id,
        ]);

        $payload = $annex->buildTemplatePayload();

        $this->assertStringContainsString('Zmiana programu', $payload['annex_change_types']);
        $this->assertStringContainsString('Kino', $payload['annex_program_text']);
        $this->assertSame('UM/2026/00001', $payload['parent_agreement_number']);
        $this->assertSame('Nowy punkt', $payload['annex_program_change_notes']);
    }

    public function test_program_snapshot_service_formats_html(): void
    {
        $html = app(ContractAnnexService::class)->formatProgramSnapshotHtml([
            'points' => [
                ['day' => 2, 'name' => 'Spacer', 'start_time' => '15:00', 'children' => []],
            ],
        ]);

        $this->assertStringContainsString('Spacer', $html);
        $this->assertStringContainsString('Dzień 2', $html);
    }
}
