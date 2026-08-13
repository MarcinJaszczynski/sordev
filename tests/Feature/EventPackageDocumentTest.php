<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPackageDocument;
use App\Models\User;
use App\Services\EventPackageDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventPackageDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_download_works_without_package_row(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'name' => 'Live PDF',
        ]);

        $this->assertDatabaseMissing('event_package_documents', [
            'event_id' => $event->id,
            'audience' => 'pilot',
        ]);

        $response = $this->get(route('admin.events.pdf', [
            'event' => $event->id,
            'audience' => 'pilot',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_overrides_intro_appears_in_rendered_html(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'name' => 'Overrides PDF',
        ]);

        $service = app(EventPackageDocumentService::class);
        $service->saveDraft($event, EventPackageDocument::AUDIENCE_PILOT, [
            'edit_mode' => EventPackageDocument::EDIT_OVERRIDES,
            'status' => EventPackageDocument::STATUS_DRAFT,
            'intro_html' => '<p>UWAGA TESTOWA INTRO</p>',
            'extra_notes_html' => '<p>UWAGA TESTOWA EXTRA</p>',
            'hide_sections' => ['pilot_set_finance'],
        ]);

        $html = $service->renderLiveHtml($event->fresh(), EventPackageDocument::AUDIENCE_PILOT);

        $this->assertStringContainsString('UWAGA TESTOWA INTRO', $html);
        $this->assertStringContainsString('UWAGA TESTOWA EXTRA', $html);
    }

    public function test_upload_mode_returns_uploaded_pdf_bytes(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'name' => 'Upload PDF',
        ]);

        $relative = 'event-package-uploads/'.$event->id.'/custom.pdf';
        Storage::disk('public')->put($relative, '%PDF-1.4 custom-package-bytes');

        app(EventPackageDocumentService::class)->saveDraft($event, EventPackageDocument::AUDIENCE_HOTEL, [
            'edit_mode' => EventPackageDocument::EDIT_UPLOAD,
            'status' => EventPackageDocument::STATUS_READY,
            'upload_path' => $relative,
        ]);

        $response = $this->get(route('admin.events.pdf', [
            'event' => $event->id,
            'audience' => 'hotel',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('custom-package-bytes', $response->getContent());
    }

    public function test_invoices_without_files_redirect_instead_of_404(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'name' => 'Bez faktur',
        ]);

        $response = $this->get(route('admin.events.invoices.pdf', ['event' => $event->id]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
