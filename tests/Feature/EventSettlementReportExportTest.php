<?php

namespace Tests\Feature;

use App\Exports\EventSettlementReportExport;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Services\EventSettlementReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class EventSettlementReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_service_builds_payload(): void
    {
        $event = Event::factory()->create(['use_manual_transport_cost' => true, 'manual_transport_cost' => 1000]);
        EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();

        $report = app(EventSettlementReportService::class)->build($event);

        $this->assertArrayHasKey('dashboard', $report);
        $this->assertArrayHasKey('participant_aggregate', $report);
        $this->assertSame($event->id, $report['event']['id']);
    }

    public function test_excel_export_has_sheets(): void
    {
        Excel::fake();

        $event = Event::factory()->create();
        EventSettlement::findOrCreateActiveForEvent($event);

        Excel::download(new EventSettlementReportExport($event), 'raport.xlsx');

        Excel::assertDownloaded('raport.xlsx');
    }
}
