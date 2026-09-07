<?php

namespace Tests\Unit\Support\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Support\Tasks\TaskQueryFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskQueryFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
    }

    public function test_mine_includes_tasks_authored_or_assigned_to_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $authored = Task::create([
            'title' => 'Zlecone przeze mnie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $other->id,
        ]);

        $assigned = Task::create([
            'title' => 'Dla mnie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $other->id,
            'assignee_id' => $user->id,
        ]);

        Task::create([
            'title' => 'Obce',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $other->id,
            'assignee_id' => $other->id,
        ]);

        $ids = TaskQueryFilters::mine(Task::query(), $user->id)
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing([$authored->id, $assigned->id], $ids);
    }

    public function test_exclude_finished_hides_completed_and_cancelled_tasks(): void
    {
        $user = User::factory()->create();
        $completedId = \App\Models\TaskStatus::query()->where('name', 'Zakończone')->value('id');
        $cancelledId = \App\Models\TaskStatus::query()->where('name', 'Anulowane')->value('id');

        $open = Task::create([
            'title' => 'Otwarte',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        $completed = Task::create([
            'title' => 'Zakończone',
            'status_id' => $completedId,
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        $cancelled = Task::create([
            'title' => 'Anulowane',
            'status_id' => $cancelledId,
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        $ids = TaskQueryFilters::excludeFinished(Task::query())
            ->pluck('id')
            ->all();

        $this->assertContains($open->id, $ids);
        $this->assertNotContains($completed->id, $ids);
        $this->assertNotContains($cancelled->id, $ids);
    }

    public function test_only_finished_keeps_completed_and_cancelled(): void
    {
        $user = User::factory()->create();
        $completedId = \App\Models\TaskStatus::query()->where('name', 'Zakończone')->value('id');
        $cancelledId = \App\Models\TaskStatus::query()->where('name', 'Anulowane')->value('id');

        $open = Task::create([
            'title' => 'Otwarte only-finished',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        $completed = Task::create([
            'title' => 'Zakończone only-finished',
            'status_id' => $completedId,
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        $cancelled = Task::create([
            'title' => 'Anulowane only-finished',
            'status_id' => $cancelledId,
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        $ids = TaskQueryFilters::onlyFinished(Task::query())
            ->pluck('id')
            ->all();

        $this->assertNotContains($open->id, $ids);
        $this->assertContains($completed->id, $ids);
        $this->assertContains($cancelled->id, $ids);
    }

    public function test_apply_ownership_scope_filters_assigned_and_authored_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $assigned = Task::create([
            'title' => 'Przypisane do mnie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $other->id,
            'assignee_id' => $user->id,
        ]);

        $authored = Task::create([
            'title' => 'Zlecone przeze mnie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $other->id,
        ]);

        Task::create([
            'title' => 'Obce',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $other->id,
            'assignee_id' => $other->id,
        ]);

        $assignedIds = TaskQueryFilters::applyOwnershipScope(Task::query(), 'assigned', $user->id)
            ->pluck('id')
            ->all();

        $authoredIds = TaskQueryFilters::applyOwnershipScope(Task::query(), 'authored', $user->id)
            ->pluck('id')
            ->all();

        $allIds = TaskQueryFilters::applyOwnershipScope(Task::query(), 'all', $user->id)
            ->pluck('id')
            ->all();

        // Scope "assigned"/"Moje" = skrzynka jak topbar (autor ∪ assignee ∪ system).
        $this->assertEqualsCanonicalizing([$assigned->id, $authored->id], $assignedIds);
        $this->assertEqualsCanonicalizing([$authored->id], $authoredIds);
        $this->assertCount(3, $allIds);

        $forMeIds = TaskQueryFilters::applyOwnershipScope(Task::query(), 'for_me', $user->id)
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing([$assigned->id], $forMeIds);
    }

    public function test_authored_scope_excludes_system_copies_even_when_author_id_matches(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $officeAuthored = Task::create([
            'title' => 'Biurowe zlecone',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'source' => 'office',
            'author_id' => $user->id,
            'assignee_id' => $other->id,
        ]);

        $systemCopy = Task::create([
            'title' => 'Systemowa kopia',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'source' => 'system',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        $authoredIds = TaskQueryFilters::applyOwnershipScope(Task::query(), 'authored', $user->id)
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing([$officeAuthored->id], $authoredIds);
        $this->assertNotContains($systemCopy->id, $authoredIds);
    }

    public function test_inbox_for_matches_author_assignee_and_allowed_system(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'biuro', 'guard_name' => 'web']);

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('biuro');
        $other = User::factory()->create();

        $authored = Task::create([
            'title' => 'Autor',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'source' => 'office',
            'author_id' => $user->id,
            'assignee_id' => $other->id,
        ]);

        $assigned = Task::create([
            'title' => 'Assignee',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'source' => 'office',
            'author_id' => $other->id,
            'assignee_id' => $user->id,
        ]);

        $sharedSystem = Task::create([
            'title' => 'Impreza potwierdzona',
            'description' => "Lista.\n\nevent-status:9:confirmed",
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'urgent',
            'source' => 'system',
            'author_id' => $other->id,
            'assignee_id' => $other->id,
        ]);

        Task::create([
            'title' => 'Obce',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'source' => 'office',
            'author_id' => $other->id,
            'assignee_id' => $other->id,
        ]);

        $ids = TaskQueryFilters::inboxFor(Task::query(), $user)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$authored->id, $assigned->id], $ids);
        $this->assertNotContains($sharedSystem->id, $ids);
    }

    public function test_assigned_scope_does_not_include_other_users_system_tasks(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'biuro', 'guard_name' => 'web']);

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('biuro');
        $other = User::factory()->create();
        $this->actingAs($user);

        $mine = Task::create([
            'title' => 'Moje',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'source' => 'office',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        $sharedSystem = Task::create([
            'title' => 'Impreza potwierdzona',
            'description' => "Lista.\n\nevent-status:9:confirmed",
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'urgent',
            'source' => 'system',
            'author_id' => $other->id,
            'assignee_id' => $other->id,
        ]);

        $disallowedSystem = Task::create([
            'title' => 'Zaliczka',
            'description' => '[payment-reminder:settlement_cost:1:advance]',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'urgent',
            'source' => 'system',
            'author_id' => $other->id,
            'assignee_id' => $other->id,
        ]);

        $ids = TaskQueryFilters::applyOwnershipScope(Task::query(), 'assigned', $user->id)
            ->pluck('id')
            ->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($sharedSystem->id, $ids);
        $this->assertNotContains($disallowedSystem->id, $ids);
    }

    public function test_top_level_only_excludes_subtasks(): void
    {
        $user = User::factory()->create();

        $parent = Task::create([
            'title' => 'Rodzic',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        Task::create([
            'title' => 'Podzadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'parent_id' => $parent->id,
        ]);

        $ids = TaskQueryFilters::topLevelOnly(Task::query())->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$parent->id], $ids);
    }

    public function test_order_by_latest_activity_prioritizes_recent_attachment_over_older_task(): void
    {
        $user = User::factory()->create();

        $olderTask = Task::create([
            'title' => 'Starsze zadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        $newerTask = Task::create([
            'title' => 'Nowsze zadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        \App\Models\TaskAttachment::create([
            'task_id' => $olderTask->id,
            'user_id' => $user->id,
            'name' => 'plik.pdf',
            'file_path' => 'task-attachments/plik.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ids = TaskQueryFilters::orderByLatestActivityDesc(Task::query())
            ->pluck('id')
            ->all();

        $this->assertSame($olderTask->id, $ids[0]);
        $this->assertSame($newerTask->id, $ids[1]);
    }

    public function test_order_by_latest_activity_prioritizes_recent_comment(): void
    {
        $user = User::factory()->create();

        $olderTask = Task::create([
            'title' => 'Starsze zadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        $newerTask = Task::create([
            'title' => 'Nowsze zadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        \App\Models\TaskComment::create([
            'task_id' => $olderTask->id,
            'user_id' => $user->id,
            'content' => 'Nowy komentarz',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ids = TaskQueryFilters::orderByLatestActivityDesc(Task::query())
            ->pluck('id')
            ->all();

        $this->assertSame($olderTask->id, $ids[0]);
    }

    public function test_creating_subtask_does_not_bump_parent_latest_activity(): void
    {
        $user = User::factory()->create();

        $parent = Task::create([
            'title' => 'Rodzic',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);
        $parent->forceFill([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ])->saveQuietly();

        $other = Task::create([
            'title' => 'Inne zadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);
        $other->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->saveQuietly();

        $subtask = Task::create([
            'title' => 'Podzadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'parent_id' => $parent->id,
        ]);
        $subtask->forceFill([
            'created_at' => now(),
            'updated_at' => now(),
        ])->saveQuietly();

        $ids = TaskQueryFilters::orderByLatestActivityDesc(Task::query())
            ->pluck('id')
            ->all();

        $this->assertSame([$subtask->id, $other->id, $parent->id], $ids);
    }

    public function test_due_and_source_quick_filters(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->startOfWeek(\Illuminate\Support\Carbon::MONDAY)->addDays(2)->setTime(12, 0));

        $overdue = Task::create([
            'title' => 'Po terminie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'urgent',
            'source' => 'office',
            'author_id' => $user->id,
            'due_date' => now()->subDay(),
        ]);

        $today = Task::create([
            'title' => 'Dziś',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'source' => 'system',
            'author_id' => $user->id,
            'due_date' => now(),
        ]);

        $noDue = Task::create([
            'title' => 'Bez terminu',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'source' => 'office',
            'author_id' => $user->id,
            'due_date' => null,
        ]);

        $this->assertEqualsCanonicalizing(
            [$overdue->id],
            TaskQueryFilters::applyDueFilter(Task::query(), 'overdue')->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$today->id],
            TaskQueryFilters::applyDueFilter(Task::query(), 'today')->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$noDue->id],
            TaskQueryFilters::applyDueFilter(Task::query(), 'no_due_date')->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$overdue->id, $noDue->id],
            TaskQueryFilters::applySourceFilter(Task::query(), 'office')->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$overdue->id],
            TaskQueryFilters::applyQuickFilters(Task::query(), 'all', onlyUrgent: true)->pluck('id')->all(),
        );
    }
}
