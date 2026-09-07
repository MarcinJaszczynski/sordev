<?php

namespace Tests\Feature;

use App\Livewire\EventProgramDayTree;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventProgramDayTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_edit_updates_program_point_fields(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['duration_days' => 3]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Stary punkt',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'include_in_program' => true,
            'active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(EventProgramDayTree::class, ['eventId' => $event->id])
            ->call('openEdit', $point->id)
            ->set('editForm.name', 'Nowy punkt')
            ->set('editForm.day', 2)
            ->set('editForm.start_time', '11:00')
            ->set('editForm.end_time', '12:00')
            ->call('saveEdit')
            ->assertSet('showEditModal', false);

        $point->refresh();
        $this->assertSame('Nowy punkt', $point->name);
        $this->assertSame(2, (int) $point->day);
        $this->assertSame('11:00', substr((string) $point->start_time, 0, 5));
        $this->assertSame('12:00', substr((string) $point->end_time, 0, 5));
    }

    public function test_delete_point_soft_deletes_record(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Do usunięcia',
            'include_in_program' => true,
            'active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(EventProgramDayTree::class, ['eventId' => $event->id])
            ->call('deletePoint', $point->id);

        $this->assertSoftDeleted('event_program_points', ['id' => $point->id]);
    }

    public function test_save_add_creates_blank_block(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['duration_days' => 2]);

        Livewire::actingAs($user)
            ->test(EventProgramDayTree::class, ['eventId' => $event->id])
            ->call('openAddBlock', 2)
            ->set('addForm.name', 'Własny blok')
            ->call('saveAdd')
            ->assertSet('showAddModal', false);

        $this->assertDatabaseHas('event_program_points', [
            'event_id' => $event->id,
            'name' => 'Własny blok',
            'day' => 2,
            'parent_id' => null,
            'include_in_program' => 1,
            'include_in_calculation' => 1,
        ]);
    }

    public function test_save_add_ignores_runaway_day_and_uses_active_day(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-03',
            'duration_days' => 3,
        ]);

        Livewire::actingAs($user)
            ->test(EventProgramDayTree::class, ['eventId' => $event->id])
            ->call('setActiveDay', 2)
            ->call('openAddBlock', 2)
            ->set('addForm.name', 'Nie twórz dnia 9')
            ->set('addForm.day', 9)
            ->call('saveAdd')
            ->assertSet('showAddModal', false);

        $this->assertDatabaseHas('event_program_points', [
            'event_id' => $event->id,
            'name' => 'Nie twórz dnia 9',
            'day' => 2,
        ]);
        $this->assertSame(3, $event->fresh()->resolveProgramDaysCount());
    }

    public function test_save_add_from_template_clones_set_children(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['duration_days' => 1]);

        $parentTemplate = EventTemplateProgramPoint::factory()->create(['name' => 'Set szablonu']);
        $childTemplate = EventTemplateProgramPoint::factory()->create(['name' => 'Podpunkt szablonu']);
        $parentTemplate->children()->attach($childTemplate->id, ['order' => 1]);

        Livewire::actingAs($user)
            ->test(EventProgramDayTree::class, ['eventId' => $event->id])
            ->call('openAddBlock', 1)
            ->call('selectTemplatePoint', $parentTemplate->id)
            ->call('saveAdd')
            ->assertSet('showAddModal', false);

        $parent = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->whereNull('parent_id')
            ->first();

        $this->assertNotNull($parent);
        $this->assertSame('Set szablonu', $parent->name);

        $child = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('parent_id', $parent->id)
            ->first();

        $this->assertNotNull($child);
        $this->assertSame('Podpunkt szablonu', $child->name);
    }

    public function test_save_add_attaches_existing_block_to_set(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $set = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Set',
            'include_in_program' => true,
            'active' => true,
        ]);

        $block = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 2,
            'name' => 'Blok do przypięcia',
            'include_in_program' => true,
            'active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(EventProgramDayTree::class, ['eventId' => $event->id])
            ->call('openAddChild', $set->id)
            ->set('addForm.existing_point_id', $block->id)
            ->call('saveAdd')
            ->assertSet('showAddModal', false);

        $block->refresh();
        $this->assertSame($set->id, (int) $block->parent_id);
        $this->assertSame(1, (int) $block->order);
    }

    public function test_save_add_applies_start_and_end_times(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        Livewire::actingAs($user)
            ->test(EventProgramDayTree::class, ['eventId' => $event->id])
            ->call('openAddBlock', 1)
            ->set('addForm.name', 'Ze slotami')
            ->set('addForm.start_time', '08:00')
            ->set('addForm.end_time', '09:00')
            ->call('saveAdd');

        $point = EventProgramPoint::query()->where('event_id', $event->id)->first();
        $this->assertNotNull($point);
        $this->assertSame('08:00', substr((string) $point->start_time, 0, 5));
        $this->assertSame('09:00', substr((string) $point->end_time, 0, 5));
    }

    public function test_detach_from_set_moves_child_to_day_blocks(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $set = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Set',
            'include_in_program' => true,
            'active' => true,
        ]);

        $child = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'parent_id' => $set->id,
            'name' => 'Podpunkt',
            'include_in_program' => true,
            'active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(EventProgramDayTree::class, ['eventId' => $event->id])
            ->call('detachFromSet', $child->id);

        $child->refresh();
        $this->assertNull($child->parent_id);
        $this->assertSame(2, (int) $child->order);
    }

    public function test_toggle_point_property_updates_flag(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Punkt',
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(EventProgramDayTree::class, ['eventId' => $event->id])
            ->assertSee('Prog')
            ->assertSee('Kalk')
            ->call('togglePointProperty', $point->id, 'include_in_calculation');

        $point->refresh();
        $this->assertFalse($point->include_in_calculation);
    }

    public function test_bulk_set_property_updates_selected_points(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['duration_days' => 2]);

        $a = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'A',
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $b = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 2,
            'name' => 'B',
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(EventProgramDayTree::class, ['eventId' => $event->id])
            ->set('selectedPointIds', [$a->id, $b->id])
            ->call('bulkSetProperty', 'include_in_calculation', false);

        $this->assertFalse($a->fresh()->include_in_calculation);
        $this->assertFalse($b->fresh()->include_in_calculation);
    }

    public function test_set_active_day_switches_tab(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['duration_days' => 3]);

        Livewire::actingAs($user)
            ->test(EventProgramDayTree::class, ['eventId' => $event->id])
            ->call('setActiveDay', 2)
            ->assertSet('activeDay', 2)
            ->assertSet('selectedPointIds', []);
    }

    public function test_duplicate_point_creates_copy_after_original(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Zwiedzanie',
            'include_in_program' => true,
            'active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(EventProgramDayTree::class, ['eventId' => $event->id])
            ->call('duplicatePoint', $point->id);

        $this->assertDatabaseHas('event_program_points', [
            'event_id' => $event->id,
            'name' => 'Zwiedzanie (kopia)',
            'order' => 2,
        ]);
    }
}
