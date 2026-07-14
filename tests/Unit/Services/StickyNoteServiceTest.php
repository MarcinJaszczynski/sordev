<?php

namespace Tests\Unit\Services;

use App\Models\Event;
use App\Models\StickyNote;
use App\Models\User;
use App\Services\StickyNoteService;
use App\Support\StickyNotes\StickyNoteCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StickyNoteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stack_returns_newest_note_first(): void
    {
        $event = Event::factory()->create();
        $author = User::factory()->create();

        $older = StickyNote::factory()->create([
            'notable_type' => Event::class,
            'notable_id' => $event->id,
            'created_by' => $author->id,
            'body' => 'Starsza',
            'created_at' => now()->subHour(),
        ]);

        $newer = StickyNote::factory()->create([
            'notable_type' => Event::class,
            'notable_id' => $event->id,
            'created_by' => $author->id,
            'body' => 'Nowsza',
            'created_at' => now(),
        ]);

        $stack = app(StickyNoteService::class)->getStack($event);

        $this->assertSame($newer->id, $stack->first()->id);
        $this->assertSame($older->id, $stack->last()->id);
    }

    public function test_author_can_edit_only_latest_note(): void
    {
        $event = Event::factory()->create();
        $author = User::factory()->create();
        $other = User::factory()->create();

        $older = StickyNote::factory()->create([
            'notable_type' => Event::class,
            'notable_id' => $event->id,
            'created_by' => $author->id,
            'created_at' => now()->subHour(),
        ]);

        $latest = StickyNote::factory()->create([
            'notable_type' => Event::class,
            'notable_id' => $event->id,
            'created_by' => $author->id,
            'created_at' => now(),
        ]);

        $service = app(StickyNoteService::class);

        $this->assertFalse($service->canEdit($older, $author));
        $this->assertTrue($service->canEdit($latest, $author));
        $this->assertFalse($service->canEdit($latest, $other));
    }

    public function test_adding_new_note_blocks_editing_previous_one(): void
    {
        $event = Event::factory()->create();
        $author = User::factory()->create();
        $colleague = User::factory()->create();
        $service = app(StickyNoteService::class);

        $first = $service->addNote($event, $author, 'Pierwsza notatka', StickyNoteCategory::HOTEL);
        $this->assertTrue($service->canEdit($first, $author));

        $service->addNote($event, $colleague, 'Kolejna notatka', StickyNoteCategory::PICKUP);

        $first->refresh();
        $this->assertFalse($service->canEdit($first, $author));
    }

    public function test_update_note_succeeds_for_editable_note(): void
    {
        $event = Event::factory()->create();
        $author = User::factory()->create();
        $service = app(StickyNoteService::class);

        $note = $service->addNote($event, $author, 'Treść początkowa');

        $updated = $service->updateNote($note, $author, 'Treść po edycji', StickyNoteCategory::GRATIS);

        $this->assertSame('Treść po edycji', $updated->body);
        $this->assertSame(StickyNoteCategory::GRATIS, $updated->category);
        $this->assertNotNull($updated->edited_at);
        $this->assertSame($author->id, $updated->updated_by);
    }

    public function test_update_note_rejects_non_editable_note(): void
    {
        $event = Event::factory()->create();
        $author = User::factory()->create();
        $colleague = User::factory()->create();
        $service = app(StickyNoteService::class);

        $note = $service->addNote($event, $author, 'Pierwsza');
        $service->addNote($event, $colleague, 'Druga');

        $this->expectException(InvalidArgumentException::class);

        $service->updateNote($note->fresh(), $author, 'Próba edycji');
    }
}
