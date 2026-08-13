<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use App\Services\ProgramPointContractorBulkAssignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramPointContractorBulkAssignServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_point_assigns_only_identical_points_across_days(): void
    {
        $event = Event::factory()->create();
        $contractor = Contractor::create(['name' => 'Hotel A', 'status' => 'active']);
        $other = Contractor::create(['name' => 'Inny', 'status' => 'active']);

        $source = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'name' => 'Nocleg',
            'is_hotel' => true,
            'is_transport' => false,
            'is_hotel_service' => false,
            'event_template_program_point_id' => null,
            'contractor_id' => $contractor->id,
        ]);

        $samePointDay2 = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 2,
            'name' => 'Nocleg',
            'is_hotel' => true,
            'is_transport' => false,
            'is_hotel_service' => false,
            'event_template_program_point_id' => null,
            'contractor_id' => null,
        ]);

        $differentPoint = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 2,
            'name' => 'Przejazd',
            'is_hotel' => false,
            'is_transport' => true,
            'is_hotel_service' => false,
            'event_template_program_point_id' => null,
            'contractor_id' => $other->id,
        ]);

        $updated = app(ProgramPointContractorBulkAssignService::class)->assign(
            $source,
            $event,
            'same_point',
        );

        $this->assertSame(1, $updated);
        $this->assertSame((int) $contractor->id, (int) $samePointDay2->fresh()->contractor_id);
        $this->assertSame((int) $other->id, (int) $differentPoint->fresh()->contractor_id);
    }

    public function test_same_point_matches_by_template_point_id(): void
    {
        $event = Event::factory()->create();
        $contractor = Contractor::create(['name' => 'Muzeum', 'status' => 'active']);
        $templateA = EventTemplateProgramPoint::factory()->create();
        $templateB = EventTemplateProgramPoint::factory()->create();

        $source = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'name' => 'Zwiedzanie A',
            'event_template_program_point_id' => $templateA->id,
            'contractor_id' => $contractor->id,
        ]);

        $sameTemplate = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 3,
            'name' => 'Inna nazwa lokalna',
            'event_template_program_point_id' => $templateA->id,
            'contractor_id' => null,
        ]);

        $otherTemplate = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 3,
            'name' => 'Zwiedzanie A',
            'event_template_program_point_id' => $templateB->id,
            'contractor_id' => null,
        ]);

        $updated = app(ProgramPointContractorBulkAssignService::class)->assign(
            $source,
            $event,
            'same_point',
        );

        $this->assertSame(1, $updated);
        $this->assertSame((int) $contractor->id, (int) $sameTemplate->fresh()->contractor_id);
        $this->assertNull($otherTemplate->fresh()->contractor_id);
    }
}
