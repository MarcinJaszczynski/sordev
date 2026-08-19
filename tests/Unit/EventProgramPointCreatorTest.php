<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use App\Services\EventProgramPointCreator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventProgramPointCreatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_from_template_clones_nested_template_children(): void
    {
        $event = Event::factory()->create();

        $grandparent = EventTemplateProgramPoint::factory()->create(['name' => 'Set główny']);
        $parent = EventTemplateProgramPoint::factory()->create(['name' => 'Podset']);
        $leaf = EventTemplateProgramPoint::factory()->create(['name' => 'Liść']);

        $grandparent->children()->attach($parent->id, ['order' => 1]);
        $parent->children()->attach($leaf->id, ['order' => 1]);

        $root = app(EventProgramPointCreator::class)->addFromTemplate($event, $grandparent, 1);

        $mid = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('parent_id', $root->id)
            ->first();

        $this->assertNotNull($mid);
        $this->assertSame('Podset', $mid->name);

        $nested = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('parent_id', $mid->id)
            ->first();

        $this->assertNotNull($nested);
        $this->assertSame('Liść', $nested->name);
    }

    public function test_add_from_template_propagates_parent_times_to_children(): void
    {
        $event = Event::factory()->create();

        $parentTemplate = EventTemplateProgramPoint::factory()->create(['name' => 'Set czasowy']);
        $childTemplate = EventTemplateProgramPoint::factory()->create(['name' => 'Podpunkt']);
        $parentTemplate->children()->attach($childTemplate->id, ['order' => 1]);

        $root = app(EventProgramPointCreator::class)->addFromTemplate($event, $parentTemplate, 1, null, true, [
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $child = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('parent_id', $root->id)
            ->first();

        $this->assertNotNull($child);
        $this->assertSame('09:00', substr((string) $child->start_time, 0, 5));
        $this->assertSame('12:00', substr((string) $child->end_time, 0, 5));
    }

    public function test_add_blank_applies_time_and_toggle_options(): void
    {
        $event = Event::factory()->create();

        $point = app(EventProgramPointCreator::class)->addBlank($event, 'Wycieczka', 1, null, [
            'start_time' => '09:00',
            'end_time' => '10:00',
            'include_in_calculation' => false,
        ]);

        $this->assertSame('09:00', substr((string) $point->start_time, 0, 5));
        $this->assertSame('10:00', substr((string) $point->end_time, 0, 5));
        $this->assertFalse($point->include_in_calculation);
    }

    public function test_detach_from_parent_moves_child_to_day_root(): void
    {
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

        $detached = app(EventProgramPointCreator::class)->detachFromParent($child);

        $this->assertNull($detached->parent_id);
        $this->assertSame(2, (int) $detached->order);
    }

    public function test_duplicate_clones_set_with_children(): void
    {
        $event = Event::factory()->create();

        $set = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Set',
            'include_in_program' => true,
            'active' => true,
        ]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'parent_id' => $set->id,
            'name' => 'Podpunkt',
            'include_in_program' => true,
            'active' => true,
        ]);

        $clone = app(EventProgramPointCreator::class)->duplicate($event, $set);

        $this->assertSame('Set (kopia)', $clone->name);
        $this->assertSame(2, (int) $clone->order);

        $clonedChild = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('parent_id', $clone->id)
            ->first();

        $this->assertNotNull($clonedChild);
        $this->assertSame('Podpunkt', $clonedChild->name);
    }

    public function test_search_template_points_distinguishes_set_from_point_and_city_tag(): void
    {
        $krakow = \App\Models\Tag::query()->create([
            'name' => 'Kraków',
            'visibility' => 'internal',
            'status' => 'active',
        ]);
        $warsaw = \App\Models\Tag::query()->create([
            'name' => 'Warszawa',
            'visibility' => 'internal',
            'status' => 'active',
        ]);

        $set = EventTemplateProgramPoint::factory()->create([
            'name' => 'Rejs statkiem po Wiśle',
            'description' => 'Rejs z Wawelu do Tyńca',
            'duration_hours' => 2,
            'duration_minutes' => 30,
            'unit_price' => 45,
        ]);
        $child = EventTemplateProgramPoint::factory()->create(['name' => 'Bilet na rejs']);
        $set->children()->attach($child->id, ['order' => 1]);
        $set->tags()->attach($krakow->id);

        $point = EventTemplateProgramPoint::factory()->create([
            'name' => 'Rejs statkiem po Wiśle',
            'description' => 'Rejs z Łazienek Królewskich',
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'unit_price' => 35,
        ]);
        $point->tags()->attach($warsaw->id);

        $creator = app(EventProgramPointCreator::class);
        $results = $creator->searchTemplatePoints('rejs statkiem');
        $this->assertTrue($results->contains('id', $set->id));
        $this->assertTrue($results->contains('id', $point->id));

        $options = $creator->searchCatalogSelectOptions('rejs statkiem');
        $this->assertArrayHasKey('template_'.$set->id, $options);
        $this->assertArrayHasKey('template_'.$point->id, $options);
        $this->assertStringContainsString('Set (1)', $options['template_'.$set->id]);
        $this->assertStringContainsString('Kraków', $options['template_'.$set->id]);
        $this->assertStringContainsString('Punkt', $options['template_'.$point->id]);
        $this->assertStringContainsString('Warszawa', $options['template_'.$point->id]);
        $this->assertStringContainsString('Wawelu', $options['template_'.$set->id]);

        $cityOnly = $creator->searchTemplatePoints('Kraków');
        $this->assertTrue($cityOnly->contains('id', $set->id));
        $this->assertFalse($cityOnly->contains('id', $point->id));
    }
}
