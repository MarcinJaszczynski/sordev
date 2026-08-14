<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pilot\Pages\PilotDocumentsPage;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotDocumentsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'pilot', 'guard_name' => 'web']);
        Filament::setCurrentPanel(Filament::getPanel('pilot'));
    }

    public function test_pilot_sees_pending_document_marked_for_pilot_package(): void
    {
        if (! Schema::hasTable('event_documents')) {
            $this->markTestSkipped('Tabela event_documents nie istnieje.');
        }

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        EventDocument::query()->create([
            'event_id' => $event->id,
            'name' => 'Lista uczestników PDF',
            'file_path' => 'event-documents/lista.pdf',
            'attach_to_pilot_pdf' => true,
            'approval_status' => 'pending',
            'is_offer' => false,
        ]);

        EventDocument::query()->create([
            'event_id' => $event->id,
            'name' => 'Odrzucony plik',
            'file_path' => 'event-documents/odrzucony.pdf',
            'attach_to_pilot_pdf' => true,
            'approval_status' => 'rejected',
            'is_offer' => false,
        ]);

        EventDocument::query()->create([
            'event_id' => $event->id,
            'name' => 'Tylko teczka',
            'file_path' => 'event-documents/teczka.pdf',
            'attach_to_pilot_pdf' => false,
            'attach_to_folder_pdf' => true,
            'approval_status' => 'pending',
            'is_offer' => false,
        ]);

        $this->actingAs($pilot);

        Livewire::test(PilotDocumentsPage::class, ['event' => $event])
            ->assertOk()
            ->assertSee('Lista uczestników PDF')
            ->assertDontSee('Odrzucony plik')
            ->assertDontSee('Tylko teczka');
    }
}
