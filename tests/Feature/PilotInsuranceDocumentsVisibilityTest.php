<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pilot\Pages\PilotDocumentsPage;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\User;
use App\Services\EventPrintPdfDataFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotInsuranceDocumentsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'pilot', 'guard_name' => 'web']);
        Filament::setCurrentPanel(Filament::getPanel('pilot'));
    }

    public function test_pilot_sees_policy_and_insured_list_without_pilot_package_flag(): void
    {
        if (! Schema::hasColumn('events', 'insurance_insured_list_path')) {
            $this->markTestSkipped('Brak kolumny insurance_insured_list_path.');
        }

        Storage::fake('public');

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $policyPath = 'event-insurance/polisa-pilot.pdf';
        $listPath = 'event-insurance/lista-ubezpieczonych.pdf';
        Storage::disk('public')->put($policyPath, '%PDF-1.4 policy');
        Storage::disk('public')->put($listPath, '%PDF-1.4 list');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'insurance_document_path' => $policyPath,
            'insurance_insured_list_path' => $listPath,
        ]);

        // Dokument bez flagi pilota — nie powinien „przesłonić” sekcji ubezpieczenia.
        if (Schema::hasTable('event_documents')) {
            EventDocument::query()->create([
                'event_id' => $event->id,
                'name' => 'Tylko teczka',
                'file_path' => 'event-documents/teczka.pdf',
                'attach_to_pilot_pdf' => false,
                'attach_to_folder_pdf' => true,
                'approval_status' => 'pending',
                'is_offer' => false,
            ]);
        }

        $this->actingAs($pilot);

        Livewire::test(PilotDocumentsPage::class, ['event' => $event])
            ->assertOk()
            ->assertSee('Ubezpieczenie')
            ->assertSee('Polisa ubezpieczeniowa')
            ->assertSee('Oryginalna lista ubezpieczonych')
            ->assertDontSee('Tylko teczka');
    }

    public function test_pilot_pdf_payload_includes_insurance_files(): void
    {
        if (! Schema::hasColumn('events', 'insurance_insured_list_path')) {
            $this->markTestSkipped('Brak kolumny insurance_insured_list_path.');
        }

        Storage::fake('public');

        $policyPath = 'event-insurance/polisa-zip.pdf';
        $listPath = 'event-insurance/lista-zip.pdf';
        Storage::disk('public')->put($policyPath, 'policy-body');
        Storage::disk('public')->put($listPath, 'list-body');

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'insurance_document_path' => $policyPath,
            'insurance_insured_list_path' => $listPath,
        ]);

        $event->load(['documents', 'activeSettlement.documents']);

        $data = app(EventPrintPdfDataFactory::class)->make($event, 'pilot');
        $labels = collect($data['attachedFiles'] ?? [])->pluck('document_label')->all();

        $this->assertContains('Polisa ubezpieczeniowa', $labels);
        $this->assertContains('Oryginalna lista ubezpieczonych', $labels);
    }

    public function test_saving_insured_list_persists_on_event(): void
    {
        if (! Schema::hasColumn('events', 'insurance_insured_list_path')) {
            $this->markTestSkipped('Brak kolumny insurance_insured_list_path.');
        }

        Storage::fake('public');

        $listPath = 'event-insurance/lista-save.pdf';
        Storage::disk('public')->put($listPath, 'list');

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $event->updateInsuranceFromFormData([
            'insurance_policy_number' => 'POL-L',
            'insurance_status' => 'in_progress',
            'insurance_payment_status' => 'pending',
            'insurance_amount' => null,
            'insurance_paid_at' => null,
            'insurance_document_path' => null,
            'insurance_insured_list_path' => $listPath,
            'insurance_terms' => null,
        ]);

        $event->refresh();

        $this->assertSame($listPath, $event->insurance_insured_list_path);
        $files = $event->insuranceFilesForPilot();
        $this->assertCount(1, $files);
        $this->assertStringStartsWith('insured_list', $files[0]['key']);
    }
}
