<?php

namespace Tests\Feature;

use App\Filament\Pages\OperationsCalendarPage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationsCalendarPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_open_calendar_task_entry_mounts_edit_action(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $task = Task::create([
            'title' => 'Zadanie z kalendarza',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(OperationsCalendarPage::class)
            ->call('openCalendarEntry', 'task-'.$task->id)
            ->assertSet('editingTaskId', $task->id)
            ->assertSet('mountedActions', ['editTask']);
    }

    public function test_calendar_defaults_to_all_tasks_scope(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $other = User::factory()->create();

        Task::create([
            'title' => 'Przypisane z kalendarza',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $other->id,
            'assignee_id' => $user->id,
        ]);

        Task::create([
            'title' => 'Obce z kalendarza',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $other->id,
            'assignee_id' => $other->id,
        ]);

        $component = Livewire::actingAs($user)
            ->test(OperationsCalendarPage::class)
            ->assertSet('tasksScope', 'all');

        $taskEvents = collect($component->instance()->calendarEvents)
            ->filter(fn (array $event): bool => ($event['type'] ?? null) === 'tasks');

        $this->assertCount(2, $taskEvents);
    }

    public function test_calendar_persists_filter_layout_in_session(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        Livewire::actingAs($user)
            ->test(OperationsCalendarPage::class)
            ->call('toggleType', 'ksef')
            ->call('setTasksScope', 'assigned')
            ->set('tasksOnlyUrgent', true)
            ->call('setLayoutMode', 'resources');

        $second = Livewire::actingAs($user)
            ->test(OperationsCalendarPage::class)
            ->assertSet('tasksScope', 'assigned')
            ->assertSet('tasksOnlyUrgent', true)
            ->assertSet('layoutMode', 'resources');

        $types = $second->get('enabledTypes');
        $this->assertIsArray($types);
        $this->assertNotContains('ksef', $types);
        $this->assertContains('insurances', $types);

        $user->refresh();
        session()->flush();

        $afterLogout = Livewire::actingAs($user)
            ->test(OperationsCalendarPage::class)
            ->assertSet('tasksScope', 'assigned')
            ->assertSet('tasksOnlyUrgent', true)
            ->assertSet('layoutMode', 'resources');

        $this->assertNotContains('ksef', $afterLogout->get('enabledTypes'));
    }

    public function test_calendar_includes_insurance_entries_without_schema_change(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = \App\Models\Event::factory()->create([
            'assigned_to' => $user->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(12)->toDateString(),
            'code' => 'UBZ-1',
        ]);

        $insurance = \App\Models\Insurance::query()->create([
            'name' => 'NNW kalendarz',
            'coverage_type' => \App\Models\Insurance::COVERAGE_NNW,
            'price_per_person' => 10,
            'active' => true,
        ]);

        $dayInsurance = \App\Models\EventDayInsurance::query()->create([
            'event_id' => $event->id,
            'day' => 2,
            'insurance_id' => $insurance->id,
            'is_done' => false,
        ]);

        $component = Livewire::actingAs($user)
            ->test(OperationsCalendarPage::class);

        $entry = collect($component->instance()->calendarEvents)
            ->firstWhere('id', 'insurance-'.$dayInsurance->id);

        $this->assertNotNull($entry);
        $this->assertSame('insurances', $entry['type']);
        $this->assertSame(
            $event->start_date->copy()->addDay()->toDateString(),
            $entry['start']
        );
        $this->assertStringContainsString('NNW kalendarz', (string) $entry['title']);
    }

    public function test_calendar_assigned_scope_includes_authored_tasks_like_topbar(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $other = User::factory()->create();

        Task::create([
            'title' => 'Przypisane z kalendarza',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $other->id,
            'assignee_id' => $user->id,
        ]);

        Task::create([
            'title' => 'Utworzone przeze mnie z kalendarza',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $other->id,
        ]);

        Task::create([
            'title' => 'Obce z kalendarza',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $other->id,
            'assignee_id' => $other->id,
        ]);

        $component = Livewire::actingAs($user)
            ->test(OperationsCalendarPage::class)
            ->call('setTasksScope', 'assigned');

        $titles = collect($component->instance()->calendarEvents)
            ->filter(fn (array $event): bool => ($event['type'] ?? null) === 'tasks')
            ->map(fn (array $event): string => (string) ($event['title'] ?? ''))
            ->values();

        $this->assertCount(2, $titles);
        $this->assertTrue($titles->contains(fn (string $title): bool => str_contains($title, 'Przypisane z kalendarza')));
        $this->assertTrue($titles->contains(fn (string $title): bool => str_contains($title, 'Utworzone przeze mnie z kalendarza')));
    }

    public function test_calendar_can_narrow_to_assigned_scope(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $other = User::factory()->create();

        Task::create([
            'title' => 'Przypisane z kalendarza',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $other->id,
            'assignee_id' => $user->id,
        ]);

        Task::create([
            'title' => 'Obce z kalendarza',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $other->id,
            'assignee_id' => $other->id,
        ]);

        $component = Livewire::actingAs($user)
            ->test(OperationsCalendarPage::class)
            ->call('setTasksScope', 'assigned')
            ->assertSet('tasksScope', 'assigned');

        $taskEvents = collect($component->instance()->calendarEvents)
            ->filter(fn (array $event): bool => ($event['type'] ?? null) === 'tasks');

        $this->assertCount(1, $taskEvents);
        $this->assertStringContainsString('Przypisane z kalendarza', (string) $taskEvents->first()['title']);
    }

    public function test_calendar_loads_past_events_outside_current_month(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = \App\Models\Event::factory()->create([
            'assigned_to' => $user->id,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subMonths(2)->addDays(2)->toDateString(),
        ]);

        $component = Livewire::actingAs($user)
            ->test(OperationsCalendarPage::class);

        $eventEntries = collect($component->instance()->calendarEvents)
            ->filter(fn (array $item): bool => ($item['id'] ?? null) === 'event-'.$event->id);

        $this->assertCount(1, $eventEntries);
        $this->assertSame(
            $event->start_date->toDateString(),
            $component->instance()->initialCalendarDate
        );
    }

    public function test_set_visible_range_reloads_events_for_window(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $inRange = \App\Models\Event::factory()->create([
            'assigned_to' => $user->id,
            'start_date' => '2025-01-15',
            'end_date' => '2025-01-17',
        ]);

        $outOfRange = \App\Models\Event::factory()->create([
            'assigned_to' => $user->id,
            'start_date' => '2025-06-15',
            'end_date' => '2025-06-17',
        ]);

        $component = Livewire::actingAs($user)
            ->test(OperationsCalendarPage::class)
            ->call('setVisibleRange', '2025-01-01', '2025-01-31');

        $ids = collect($component->instance()->calendarEvents)
            ->pluck('id')
            ->all();

        $this->assertContains('event-'.$inRange->id, $ids);
        $this->assertNotContains('event-'.$outOfRange->id, $ids);
    }

    public function test_reset_task_filters_shows_all_tasks_and_enables_tasks_type(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $other = User::factory()->create();

        Task::create([
            'title' => 'Przypisane z kalendarza',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $other->id,
            'assignee_id' => $user->id,
        ]);

        Task::create([
            'title' => 'Obce z kalendarza',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $other->id,
            'assignee_id' => $other->id,
        ]);

        $component = Livewire::actingAs($user)
            ->test(OperationsCalendarPage::class)
            ->call('resetTaskFilters')
            ->assertSet('tasksScope', 'all')
            ->assertSet('tasksOnlyUrgent', false);

        $taskEvents = collect($component->instance()->calendarEvents)
            ->filter(fn (array $event): bool => ($event['type'] ?? null) === 'tasks');

        $this->assertCount(2, $taskEvents);
    }

    public function test_calendar_hides_finished_tasks_by_default(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $completedId = \App\Models\TaskStatus::query()->where('name', 'Zakończone')->value('id');
        $cancelledId = \App\Models\TaskStatus::query()->where('name', 'Anulowane')->value('id');

        Task::create([
            'title' => 'Aktywne z kalendarza',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        Task::create([
            'title' => 'Zakończone z kalendarza',
            'due_date' => now()->addDay(),
            'status_id' => $completedId,
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        Task::create([
            'title' => 'Anulowane z kalendarza',
            'due_date' => now()->addDay(),
            'status_id' => $cancelledId,
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        $component = Livewire::actingAs($user)
            ->test(OperationsCalendarPage::class)
            ->assertSet('showFinishedTasks', false);

        $taskEvents = collect($component->instance()->calendarEvents)
            ->filter(fn (array $event): bool => ($event['type'] ?? null) === 'tasks');

        $this->assertCount(1, $taskEvents);
        $this->assertStringContainsString('Aktywne z kalendarza', (string) $taskEvents->first()['title']);
    }

    public function test_calendar_can_show_finished_tasks_when_filter_enabled(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $completedId = \App\Models\TaskStatus::query()->where('name', 'Zakończone')->value('id');

        Task::create([
            'title' => 'Zakończone z kalendarza',
            'due_date' => now()->addDay(),
            'status_id' => $completedId,
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        $component = Livewire::actingAs($user)
            ->test(OperationsCalendarPage::class)
            ->set('showFinishedTasks', true);

        $taskEvents = collect($component->instance()->calendarEvents)
            ->filter(fn (array $event): bool => ($event['type'] ?? null) === 'tasks');

        $this->assertCount(1, $taskEvents);
        $this->assertStringContainsString('Zakończone z kalendarza', (string) $taskEvents->first()['title']);
    }

    public function test_calendar_task_entries_do_not_expose_top_level_url(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $task = Task::create([
            'title' => 'Bez linku w url',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        $entry = collect(app(\App\Services\CalendarEventAggregator::class)->events([
            'types' => ['tasks'],
            'tasks_scope' => 'all',
            'user_id' => $user->id,
        ]))->firstWhere('id', 'task-'.$task->id);

        $this->assertNotNull($entry);
        $this->assertArrayNotHasKey('url', $entry);
        $this->assertNotEmpty($entry['links']);
        $this->assertSame(
            'Od '.$user->name.' dla —',
            $entry['extendedProps']['ownershipPreview'] ?? null,
        );
    }

    public function test_open_calendar_event_entry_mounts_context_action(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = \App\Models\Event::factory()->create([
            'assigned_to' => $user->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        Livewire::actingAs($user)
            ->test(OperationsCalendarPage::class)
            ->call('openCalendarEntry', 'event-'.$event->id)
            ->assertSet('mountedActions', ['calendarEntryContext']);
    }
}
