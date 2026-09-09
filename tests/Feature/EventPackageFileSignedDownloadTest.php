<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventTemplate;
use App\Models\User;
use App\Services\Documents\EventPackageFileSignedUrlService;
use App\Services\EventPrintPdfDataFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventPackageFileSignedDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_download_serves_file_attached_to_audience(): void
    {
        Storage::fake('public');

        $event = $this->createEvent();
        $path = 'event-docs/test-driver.txt';
        Storage::disk('public')->put($path, 'tresc-dla-kierowcy');

        $document = EventDocument::create([
            'event_id' => $event->id,
            'name' => 'Instrukcja kierowcy',
            'file_path' => $path,
            'original_filename' => 'instrukcja.txt',
            'mime_type' => 'text/plain',
            'attach_to_driver_pdf' => true,
            'attach_to_pilot_pdf' => false,
            'approval_status' => 'approved',
        ]);

        $url = app(EventPackageFileSignedUrlService::class)->make($event, 'driver', [
            'kind' => EventPackageFileSignedUrlService::KIND_EVENT_DOCUMENT,
            'ref' => $document->id,
            'file_index' => 0,
        ]);

        $response = $this->get($url);

        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }

    public function test_unsigned_url_is_forbidden(): void
    {
        $event = $this->createEvent();
        $document = EventDocument::create([
            'event_id' => $event->id,
            'name' => 'Plik',
            'file_path' => 'x.txt',
            'attach_to_driver_pdf' => true,
            'approval_status' => 'approved',
        ]);

        $response = $this->get(route('shared.events.package-files.download', [
            'event' => $event->id,
            'audience' => 'driver',
            'kind' => 'event_document',
            'ref' => $document->id,
            'fileIndex' => 0,
        ]));

        $response->assertForbidden();
    }

    public function test_wrong_audience_flag_is_forbidden(): void
    {
        Storage::fake('public');
        $event = $this->createEvent();
        $path = 'event-docs/pilot-only.txt';
        Storage::disk('public')->put($path, 'tajne');

        $document = EventDocument::create([
            'event_id' => $event->id,
            'name' => 'Tylko pilot',
            'file_path' => $path,
            'attach_to_pilot_pdf' => true,
            'attach_to_driver_pdf' => false,
            'approval_status' => 'approved',
        ]);

        $url = URL::temporarySignedRoute(
            'shared.events.package-files.download',
            now()->addDay(),
            [
                'event' => $event->id,
                'audience' => 'driver',
                'kind' => 'event_document',
                'ref' => $document->id,
                'fileIndex' => 0,
            ],
        );

        $this->get($url)->assertForbidden();
    }

    public function test_driver_package_payload_has_no_settlement_amounts_and_has_day_routes(): void
    {
        $event = $this->createEvent([
            'program_day_routes' => ['1' => 'Kraków → Zakopane'],
            'program_day_start_times' => ['1' => '07:30'],
        ]);

        $data = app(EventPrintPdfDataFactory::class)->make($event, 'driver');

        $this->assertNotEmpty($data['driverDayRoutes']);
        $this->assertSame('Kraków', $data['driverDayRoutes'][0]['from']);
        $this->assertSame('Zakopane', $data['driverDayRoutes'][0]['to']);
        $this->assertSame([], $data['settlementLedger']);
        $this->assertSame([], $data['pilotExpenseRows']);
        $this->assertSame([], $data['pilotSetFinanceCards']);

        $html = view('pdf.packages.driver', $data)->render();
        $this->assertStringContainsString('Trasa dzień po dniu', $html);
        $this->assertStringContainsString('Stan licznika', $html);
        $this->assertStringNotContainsString('Szkoła / zamawiający', $html);
        $this->assertStringNotContainsString('Rozliczenie', $html);
        $this->assertStringNotContainsString('Tabela wydatków', $html);
    }

    public function test_link_ttl_is_end_date_plus_five_days(): void
    {
        $event = $this->createEvent([
            'end_date' => now()->addDays(10)->toDateString(),
        ]);

        $expires = app(EventPackageFileSignedUrlService::class)->expiresAt($event);

        $this->assertTrue(
            $expires->isSameDay($event->end_date->copy()->addDays(5))
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEvent(array $overrides = []): Event
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');

        $template = EventTemplate::factory()->create();

        return Event::create(array_merge([
            'event_template_id' => $template->id,
            'name' => 'Impreza signed PDF',
            'client_name' => 'Klient',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'participant_count' => 20,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ], $overrides));
    }
}
