<?php

namespace Tests\Feature;

use App\Livewire\EventProgramPlanner;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\User;
use App\Services\EventProgramPointDeletionService;
use App\Services\EventProgramPointOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventProgramPointDeletionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_delete_set_parent_cascades_to_children(): void
    {
        $event = Event::factory()->create(['duration_days' => 1]);
        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Muzeum',
            'include_in_program' => true,
            'active' => true,
        ]);
        $child = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Bilety',
            'include_in_program' => true,
            'active' => true,
        ]);

        $result = app(EventProgramPointDeletionService::class)->softDelete($parent);

        $this->assertTrue($result['was_set']);
        $this->assertSame(1, $result['child_count']);
        $this->assertTrue($parent->fresh()->trashed());
        $this->assertTrue($child->fresh()->trashed());

        $visible = app(EventProgramPointOrderService::class)->visibleProgramPoints($event);
        $this->assertFalse($visible->contains('id', $parent->id));
        $this->assertFalse($visible->contains('id', $child->id));
    }

    public function test_restore_set_parent_restores_children(): void
    {
        $event = Event::factory()->create(['duration_days' => 1]);
        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'name' => 'Muzeum',
            'include_in_program' => true,
            'active' => true,
        ]);
        $child = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'day' => 1,
            'name' => 'Bilety',
            'include_in_program' => true,
            'active' => true,
        ]);

        $service = app(EventProgramPointDeletionService::class);
        $service->softDelete($parent);

        $restored = $service->restore($parent->fresh());

        $this->assertSame(2, $restored);
        $this->assertFalse($parent->fresh()->trashed());
        $this->assertFalse($child->fresh()->trashed());
    }

    public function test_undo_restores_last_deleted_set(): void
    {
        $event = Event::factory()->create(['duration_days' => 1]);
        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'name' => 'Muzeum',
            'include_in_program' => true,
            'active' => true,
        ]);
        $child = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'day' => 1,
            'name' => 'Bilety',
            'include_in_program' => true,
            'active' => true,
        ]);

        $service = app(EventProgramPointDeletionService::class);
        $result = $service->softDelete($parent);

        $this->assertTrue($service->hasPendingUndo((int) $event->id));

        $restored = $service->restoreByIds((int) $event->id, $result['point_ids']);

        $this->assertSame(2, $restored);
        $this->assertFalse($service->hasPendingUndo((int) $event->id));
        $this->assertFalse($parent->fresh()->trashed());
        $this->assertFalse($child->fresh()->trashed());
    }

    public function test_cleanup_orphans_soft_deletes_children_of_trashed_parent(): void
    {
        $event = Event::factory()->create(['duration_days' => 1]);
        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'name' => 'Muzeum',
            'include_in_program' => true,
            'active' => true,
        ]);
        $child = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'day' => 1,
            'name' => 'Bilety',
            'include_in_program' => true,
            'active' => true,
        ]);

        // Legacy half-delete: only parent soft-deleted.
        $parent->delete();
        $this->assertFalse($child->fresh()->trashed());

        $cleaned = app(EventProgramPointDeletionService::class)->cleanupOrphans($event);

        $this->assertSame(1, $cleaned);
        $this->assertTrue($child->fresh()->trashed());

        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(EventProgramPlanner::class, ['eventId' => $event->id]);
        $titles = collect($component->instance()->render()->getData()['plannerData']['events'] ?? [])
            ->pluck('title')
            ->implode(' ');

        $this->assertStringNotContainsString('Muzeum', $titles);
        $this->assertStringNotContainsString('Bilety', $titles);
    }

    public function test_describe_deletion_distinguishes_set_and_point(): void
    {
        $event = Event::factory()->create(['duration_days' => 1]);
        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'name' => 'Muzeum',
        ]);
        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'day' => 1,
            'name' => 'Bilety',
        ]);
        $single = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'name' => 'Przejazd',
        ]);

        $service = app(EventProgramPointDeletionService::class);

        $setDescription = $service->describeDeletion($parent->fresh(['children', 'templatePoint']));
        $this->assertTrue($setDescription['was_set']);
        $this->assertStringContainsString('cały set', $setDescription['heading']);

        $pointDescription = $service->describeDeletion($single->fresh(['children', 'templatePoint']));
        $this->assertFalse($pointDescription['was_set']);
        $this->assertStringContainsString('punkt', $pointDescription['heading']);
    }
}
