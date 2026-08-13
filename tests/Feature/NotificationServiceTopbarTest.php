<?php

namespace Tests\Feature;

use App\Enums\TaskSource;
use App\Models\ClientInvoiceRequest;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Place;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\UserNotificationRead;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationServiceTopbarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        foreach (['admin', 'ksiegowosc', 'biuro', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function createEventForUser(User $user, string $status, string $name): Event
    {
        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'start_place_id' => $place->id,
        ]);

        return Event::create([
            'event_template_id' => $template->id,
            'start_place_id' => $place->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'name' => $name,
            'client_name' => 'Klient testowy',
            'start_date' => '2026-05-10',
            'end_date' => '2026-05-12',
            'duration_days' => 3,
            'participant_count' => 20,
            'total_cost' => 3000,
            'status' => $status,
        ]);
    }

    public function test_topbar_counts_include_new_and_pending_cancellation_events(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->createEventForUser($user, Event::STATUS_INQUIRY, 'Nowa impreza');
        $this->createEventForUser($user, Event::STATUS_PENDING_CANCELLATION, 'Impreza do anulacji');
        $this->createEventForUser($user, Event::STATUS_CONFIRMED, 'Potwierdzona impreza');

        $otherUser = User::factory()->create();
        $this->createEventForUser($otherUser, Event::STATUS_INQUIRY, 'Nieprzypisana dla użytkownika');

        NotificationService::clearCacheForUser($user->id);
        $data = NotificationService::getTopbarDataForUser($user->id);

        $this->assertSame(1, $data['counts']['new_events']);
        $this->assertSame(1, $data['counts']['pending_cancellation_events']);
        $this->assertSame(1, $data['counts']['confirmed_events']);
        $this->assertArrayHasKey('new_event', $data['items_by_type']);
        $this->assertArrayHasKey('pending_cancellation_event', $data['items_by_type']);
        $this->assertCount(1, $data['items_by_type']['new_event']);
        $this->assertCount(1, $data['items_by_type']['pending_cancellation_event']);
    }

    public function test_event_counts_are_unread_only(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $eventA = $this->createEventForUser($user, Event::STATUS_INQUIRY, 'Impreza A');
        $this->createEventForUser($user, Event::STATUS_INQUIRY, 'Impreza B');

        NotificationService::clearCacheForUser($user->id);
        $data = NotificationService::getTopbarDataForUser($user->id);
        $this->assertSame(2, $data['counts']['new_events']);

        $item = collect($data['items_by_type']['new_event'])
            ->first(fn (array $row): bool => (int) ($row['id'] ?? 0) === $eventA->id);

        $this->assertNotNull($item);
        NotificationService::markAsRead($user->id, $item['fingerprint']);

        $data = NotificationService::getTopbarDataForUser($user->id, fresh: true);
        $this->assertSame(1, $data['counts']['new_events']);
        $this->assertCount(1, $data['items_by_type']['new_event']);
    }

    public function test_task_notifications_include_mine_scope_for_assignee_and_author(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $statusId = TaskStatus::query()->where('name', 'Do zrobienia')->value('id');

        $readAssigned = Task::factory()->create([
            'assignee_id' => $user->id,
            'author_id' => $other->id,
            'status_id' => $statusId,
            'title' => 'Odczytane przypisane',
        ]);

        $newAssigned = Task::factory()->create([
            'assignee_id' => $user->id,
            'author_id' => $other->id,
            'status_id' => $statusId,
            'title' => 'Nowe przypisane',
        ]);

        $newAuthored = Task::factory()->create([
            'assignee_id' => $other->id,
            'author_id' => $user->id,
            'status_id' => $statusId,
            'title' => 'Moje utworzone',
        ]);

        Task::factory()->create([
            'assignee_id' => $other->id,
            'author_id' => $other->id,
            'status_id' => $statusId,
            'title' => 'Obce zadanie',
        ]);

        NotificationService::clearCacheForUser($user->id);
        $allData = NotificationService::getTopbarDataForUser($user->id, fresh: true);
        $readItem = collect($allData['items_by_type']['task'])
            ->first(fn (array $row): bool => (int) ($row['id'] ?? 0) === $readAssigned->id);
        $this->assertNotNull($readItem);
        NotificationService::markAsRead($user->id, $readItem['fingerprint']);

        NotificationService::clearCacheForUser($user->id);
        $data = NotificationService::getTopbarDataForUser($user->id, fresh: true);

        $this->assertSame(2, $data['counts']['tasks']);
        $this->assertCount(2, $data['items_by_type']['task']);

        $titles = collect($data['items_by_type']['task'])->pluck('title')->all();
        $this->assertContains('Nowe przypisane', $titles);
        $this->assertContains('Moje utworzone', $titles);
    }

    public function test_shared_system_task_stays_unread_for_other_office_users(): void
    {
        $assignee = User::factory()->create();
        $assignee->assignRole('admin');
        $colleague = User::factory()->create();
        $colleague->assignRole('biuro');
        $statusId = TaskStatus::query()->where('name', 'Do zrobienia')->value('id');

        $task = Task::factory()->create([
            'title' => 'Impreza potwierdzona — lista kontrolna',
            'assignee_id' => $assignee->id,
            'author_id' => $assignee->id,
            'status_id' => $statusId,
            'source' => TaskSource::System->value,
        ]);

        NotificationService::clearCacheForUser($assignee->id);
        NotificationService::clearCacheForUser($colleague->id);

        $assigneeData = NotificationService::getTopbarDataForUser($assignee->id, fresh: true);
        $colleagueData = NotificationService::getTopbarDataForUser($colleague->id, fresh: true);

        $assigneeItem = collect($assigneeData['items_by_type']['task'])
            ->first(fn (array $row): bool => (int) ($row['id'] ?? 0) === $task->id);
        $colleagueItem = collect($colleagueData['items_by_type']['task'])
            ->first(fn (array $row): bool => (int) ($row['id'] ?? 0) === $task->id);

        $this->assertNotNull($assigneeItem);
        $this->assertNotNull($colleagueItem);

        NotificationService::markAsRead($assignee->id, $assigneeItem['fingerprint']);

        $assigneeAfter = NotificationService::getTopbarDataForUser($assignee->id, fresh: true);
        $colleagueAfter = NotificationService::getTopbarDataForUser($colleague->id, fresh: true);

        $this->assertFalse(
            collect($assigneeAfter['items_by_type']['task'])
                ->contains(fn (array $row): bool => (int) ($row['id'] ?? 0) === $task->id && ! ($row['is_read'] ?? false))
        );
        $this->assertSame(0, $assigneeAfter['counts']['tasks']);

        $colleagueUnread = collect($colleagueAfter['items_by_type']['task'])
            ->first(fn (array $row): bool => (int) ($row['id'] ?? 0) === $task->id);
        $this->assertNotNull($colleagueUnread);
        $this->assertFalse((bool) ($colleagueUnread['is_read'] ?? false));
        $this->assertGreaterThanOrEqual(1, $colleagueAfter['counts']['tasks']);
    }

    public function test_new_task_visible_after_create_despite_list_visit(): void
    {
        $user = User::factory()->create([
            'tasks_last_seen_at' => now(),
        ]);
        $statusId = TaskStatus::query()->where('name', 'Do zrobienia')->value('id');

        Task::factory()->create([
            'assignee_id' => $user->id,
            'author_id' => $user->id,
            'status_id' => $statusId,
            'title' => 'Wlasnie utworzone',
        ]);

        NotificationService::clearCacheForUser($user->id);
        $data = NotificationService::getTopbarDataForUser($user->id, limitPerType: 15, combinedLimit: 15, taskQueryLimit: 30, fresh: true);

        $this->assertSame(1, $data['counts']['tasks']);
        $this->assertCount(1, $data['items_by_type']['task']);
        $this->assertSame('Wlasnie utworzone', $data['items_by_type']['task'][0]['title']);
    }

    public function test_task_count_respects_query_limit_not_display_limit(): void
    {
        $user = User::factory()->create();
        $statusId = TaskStatus::query()->where('name', 'Do zrobienia')->value('id');

        for ($i = 1; $i <= 20; $i++) {
            Task::factory()->create([
                'assignee_id' => $user->id,
                'author_id' => $user->id,
                'status_id' => $statusId,
                'title' => 'Zadanie '.$i,
            ]);
        }

        NotificationService::clearCacheForUser($user->id);
        $data = NotificationService::getTopbarDataForUser($user->id, limitPerType: 15, combinedLimit: 15, taskQueryLimit: 30, fresh: true);

        $this->assertSame(20, $data['counts']['tasks']);
        $this->assertCount(15, $data['items_by_type']['task']);
    }

    public function test_task_count_is_not_capped_by_query_limit(): void
    {
        $user = User::factory()->create();
        $statusId = TaskStatus::query()->where('name', 'Do zrobienia')->value('id');

        for ($i = 1; $i <= 25; $i++) {
            Task::factory()->create([
                'assignee_id' => $user->id,
                'author_id' => $user->id,
                'status_id' => $statusId,
                'title' => 'Zadanie '.$i,
            ]);
        }

        NotificationService::clearCacheForUser($user->id);
        $data = NotificationService::getTopbarDataForUser($user->id, limitPerType: 15, combinedLimit: 15, taskQueryLimit: 10, fresh: true);

        $this->assertSame(25, $data['counts']['tasks']);
        $this->assertCount(10, $data['items_by_type']['task']);
    }

    public function test_subtask_increments_topbar_task_count_for_assignee(): void
    {
        $author = User::factory()->create();
        $assignee = User::factory()->create();
        $statusId = TaskStatus::query()->where('name', 'Do zrobienia')->value('id');

        $parent = Task::factory()->create([
            'assignee_id' => $assignee->id,
            'author_id' => $author->id,
            'status_id' => $statusId,
            'title' => 'Rodzic',
            'updated_at' => now()->subDay(),
            'created_at' => now()->subDay(),
        ]);

        NotificationService::markTaskAsRead($assignee->id, $parent);
        NotificationService::clearCacheForUser($assignee->id);

        $before = NotificationService::getTopbarDataForUser($assignee->id, limitPerType: 15, combinedLimit: 15, taskQueryLimit: 30, fresh: true);
        $this->assertSame(0, $before['counts']['tasks']);

        $subtask = Task::factory()->create([
            'assignee_id' => $assignee->id,
            'author_id' => $author->id,
            'status_id' => $statusId,
            'title' => 'Podzadanie X',
            'parent_id' => $parent->id,
        ]);
        $parent->touch();
        NotificationService::clearCacheForTaskStakeholders($subtask);

        $after = NotificationService::getTopbarDataForUser($assignee->id, limitPerType: 15, combinedLimit: 15, taskQueryLimit: 30, fresh: true);

        $this->assertSame(2, $after['counts']['tasks']);
        $titles = collect($after['items_by_type']['task'])->pluck('title')->all();
        $this->assertContains('Podzadanie X', $titles);
        $this->assertContains('Rodzic', $titles);
    }

    public function test_mark_task_as_read_clears_topbar_counter(): void
    {
        $user = User::factory()->create();
        $statusId = TaskStatus::query()->where('name', 'Do zrobienia')->value('id');

        $task = Task::factory()->create([
            'assignee_id' => $user->id,
            'author_id' => $user->id,
            'status_id' => $statusId,
            'title' => 'Do odczytania w edycji',
        ]);

        NotificationService::clearCacheForUser($user->id);
        $before = NotificationService::getTopbarDataForUser($user->id, fresh: true);
        $this->assertSame(1, $before['counts']['tasks']);

        NotificationService::markTaskAsRead($user->id, $task);

        $after = NotificationService::getTopbarDataForUser($user->id, fresh: true);
        $this->assertSame(0, $after['counts']['tasks']);
    }

    public function test_task_dropdown_lists_newest_first(): void
    {
        $user = User::factory()->create();
        $statusId = TaskStatus::query()->where('name', 'Do zrobienia')->value('id');

        $older = Task::factory()->create([
            'assignee_id' => $user->id,
            'author_id' => $user->id,
            'status_id' => $statusId,
            'title' => 'Starsze zadanie',
        ]);
        $older->forceFill([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ])->saveQuietly();

        $newer = Task::factory()->create([
            'assignee_id' => $user->id,
            'author_id' => $user->id,
            'status_id' => $statusId,
            'title' => 'Nowsze zadanie',
        ]);

        NotificationService::clearCacheForUser($user->id);
        $data = NotificationService::getTopbarDataForUser($user->id, limitPerType: 15, combinedLimit: 15, taskQueryLimit: 30, fresh: true);

        $titles = collect($data['items_by_type']['task'])->pluck('title')->all();

        $this->assertSame(['Nowsze zadanie', 'Starsze zadanie'], $titles);
    }

    public function test_task_reappears_after_update(): void
    {
        $user = User::factory()->create();
        $statusId = TaskStatus::query()->where('name', 'Do zrobienia')->value('id');

        $task = Task::factory()->create([
            'assignee_id' => $user->id,
            'author_id' => $user->id,
            'status_id' => $statusId,
            'title' => 'Zadanie testowe',
        ]);

        NotificationService::clearCacheForUser($user->id);
        $data = NotificationService::getTopbarDataForUser($user->id);
        $this->assertSame(1, $data['counts']['tasks']);

        $fingerprint = $data['items_by_type']['task'][0]['fingerprint'];
        NotificationService::markAsRead($user->id, $fingerprint);

        $data = NotificationService::getTopbarDataForUser($user->id, fresh: true);
        $this->assertSame(0, $data['counts']['tasks']);

        $task->forceFill(['updated_at' => now()->addMinute()])->save();

        NotificationService::clearCacheForUser($user->id);
        $data = NotificationService::getTopbarDataForUser($user->id, fresh: true);
        $this->assertSame(1, $data['counts']['tasks']);
    }

    public function test_comment_unread_for_my_task(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $statusId = TaskStatus::query()->where('name', 'Do zrobienia')->value('id');

        $task = Task::factory()->create([
            'assignee_id' => $user->id,
            'author_id' => $user->id,
            'status_id' => $statusId,
        ]);

        TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $other->id,
            'content' => 'Komentarz od innego użytkownika',
        ]);

        NotificationService::clearCacheForUser($user->id);
        $data = NotificationService::getTopbarDataForUser($user->id);
        $this->assertSame(1, $data['counts']['comments']);
        $this->assertCount(1, $data['items_by_type']['comment']);

        $fingerprint = $data['items_by_type']['comment'][0]['fingerprint'];
        NotificationService::markAsRead($user->id, $fingerprint);

        $data = NotificationService::getTopbarDataForUser($user->id, fresh: true);
        $this->assertSame(0, $data['counts']['comments']);
    }

    public function test_comment_notification_meta_strips_html(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $statusId = TaskStatus::query()->where('name', 'Do zrobienia')->value('id');

        $task = Task::factory()->create([
            'assignee_id' => $user->id,
            'author_id' => $user->id,
            'status_id' => $statusId,
        ]);

        TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $other->id,
            'content' => '<p>Treść komentarza</p>',
        ]);

        NotificationService::clearCacheForUser($user->id);
        $data = NotificationService::getTopbarDataForUser($user->id, fresh: true);

        $this->assertStringContainsString('skomentował zadanie', $data['items_by_type']['comment'][0]['title']);
        $this->assertStringContainsString($other->name, $data['items_by_type']['comment'][0]['title']);
        $this->assertStringContainsString('Treść komentarza', $data['items_by_type']['comment'][0]['meta']);
        $this->assertStringNotContainsString('<p>', $data['items_by_type']['comment'][0]['meta']);
    }

    public function test_new_comment_clears_assignee_notification_cache(): void
    {
        $assignee = User::factory()->create();
        $author = User::factory()->create();
        $statusId = TaskStatus::query()->where('name', 'Do zrobienia')->value('id');

        $task = Task::factory()->create([
            'assignee_id' => $assignee->id,
            'author_id' => $author->id,
            'status_id' => $statusId,
        ]);

        NotificationService::clearCacheForUser($assignee->id);
        NotificationService::getTopbarDataForUser($assignee->id, fresh: true);

        TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $author->id,
            'content' => 'Nowy komentarz dla przypisanego',
        ]);

        $data = NotificationService::getTopbarDataForUser($assignee->id, fresh: true);

        $this->assertSame(1, $data['counts']['comments']);
        $this->assertCount(1, $data['items_by_type']['comment']);
    }

    public function test_own_comment_on_owned_task_bumps_activity_counter(): void
    {
        $owner = User::factory()->create();
        $statusId = TaskStatus::query()->where('name', 'Do zrobienia')->value('id');

        $task = Task::factory()->create([
            'assignee_id' => $owner->id,
            'author_id' => $owner->id,
            'status_id' => $statusId,
            'title' => 'Moje zadanie',
        ]);
        $task->forceFill(['updated_at' => now()->subDay()])->saveQuietly();

        $updatedBefore = $task->fresh()->updated_at?->timestamp ?? 0;

        NotificationService::clearCacheForUser($owner->id);
        NotificationService::getTopbarDataForUser($owner->id, fresh: true);

        TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $owner->id,
            'content' => 'Własny komentarz ownera',
        ]);

        // W testach observer bywa niezarejestrowany (Schema::hasTable przy boot) —
        // ścieżka UI i tak woła clearCacheForTaskCommentStakeholders po create.
        $comment = TaskComment::query()->where('task_id', $task->id)->latest('id')->firstOrFail();
        NotificationService::clearCacheForTaskCommentStakeholders($comment);

        $task->refresh();
        $this->assertGreaterThan($updatedBefore, $task->updated_at?->timestamp ?? 0);

        $after = NotificationService::getTopbarDataForUser($owner->id, fresh: true);
        $taskItem = collect($after['items_by_type']['task'] ?? [])->firstWhere('id', $task->id)
            ?? collect($after['items'] ?? [])->first(fn (array $item): bool => ($item['type'] ?? '') === 'task' && (int) ($item['id'] ?? 0) === (int) $task->id);

        $this->assertNotNull($taskItem, 'Zadanie powinno wrócić do aktywności topbara po własnym komentarzu.');
        $this->assertGreaterThan($updatedBefore, (int) ($taskItem['revision'] ?? 0));
        // Własny komentarz nie wchodzi do counts.comments — bump idzie przez aktywność zadania.
        $this->assertSame(0, $after['counts']['comments']);
    }

    public function test_closing_task_modal_marks_comment_notifications_as_read(): void
    {
        $assignee = User::factory()->create();
        $assignee->assignRole('admin');
        $author = User::factory()->create();
        $statusId = TaskStatus::query()->where('name', 'Do zrobienia')->value('id');

        $task = Task::factory()->create([
            'assignee_id' => $assignee->id,
            'author_id' => $author->id,
            'status_id' => $statusId,
        ]);

        TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $author->id,
            'content' => 'Do oznaczenia jako przeczytane',
        ]);

        NotificationService::clearCacheForUser($assignee->id);
        $before = NotificationService::getTopbarDataForUser($assignee->id, fresh: true);
        $this->assertSame(1, $before['counts']['comments']);

        Livewire::actingAs($assignee)
            ->test(\App\Filament\Resources\TaskResource\Pages\ListTasks::class)
            ->call('openEditTaskModal', $task->id)
            ->call('callMountedAction');

        $afterClose = NotificationService::getTopbarDataForUser($assignee->id, fresh: true);
        $this->assertSame(0, $afterClose['counts']['comments']);
        $this->assertSame([], $afterClose['items_by_type']['comment']);
    }

    public function test_invoice_request_for_finance_roles(): void
    {
        if (! Schema::hasTable('client_invoice_requests')) {
            $this->markTestSkipped('Brak tabeli client_invoice_requests.');
        }

        $event = Event::factory()->create();
        ClientInvoiceRequest::query()->create([
            'event_id' => $event->id,
            'user_id' => User::factory()->create()->id,
            'company_name' => 'Firma Testowa',
            'nip' => '1234567890',
            'invoice_email' => 'faktury@test.local',
            'status' => ClientInvoiceRequest::STATUS_PENDING,
        ]);

        $accountant = User::factory()->create();
        $accountant->assignRole('ksiegowosc');

        NotificationService::clearCacheForUser($accountant->id);
        $financeData = NotificationService::getTopbarDataForUser($accountant->id, fresh: true);
        $this->assertSame(1, $financeData['counts']['invoice_requests']);
        $this->assertCount(1, $financeData['items_by_type']['invoice_request']);

        $regular = User::factory()->create();
        NotificationService::clearCacheForUser($regular->id);
        $regularData = NotificationService::getTopbarDataForUser($regular->id, fresh: true);
        $this->assertSame(0, $regularData['counts']['invoice_requests']);
        $this->assertSame([], $regularData['items_by_type']['invoice_request']);
    }

    public function test_mark_read_endpoint_clears_counter(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = $this->createEventForUser($user, Event::STATUS_INQUIRY, 'Do odczytania');
        NotificationService::clearCacheForUser($user->id);

        $before = $this->getJson(route('admin.notifications.counts'));
        $before->assertOk();
        $this->assertSame(1, $before->json('new_events'));

        $item = collect($before->json('items_by_type.new_event'))
            ->first(fn (array $row): bool => (int) ($row['id'] ?? 0) === $event->id);
        $this->assertNotNull($item);

        $this->postJson(route('admin.notifications.mark-read'), [
            'fingerprint' => $item['fingerprint'],
        ])->assertOk()->assertJson(['ok' => true]);

        $after = $this->getJson(route('admin.notifications.counts'));
        $after->assertOk();
        $this->assertSame(0, $after->json('new_events'));
    }

    public function test_fingerprint_uses_type_id_revision(): void
    {
        $fingerprint = UserNotificationRead::fingerprintFor([
            'type' => 'task',
            'id' => 5,
            'revision' => '1710000000',
        ]);

        $this->assertSame(
            hash('sha256', 'task|5|1710000000'),
            $fingerprint,
        );
    }

    public function test_notification_counts_endpoint_returns_new_event_counters(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->createEventForUser($user, Event::STATUS_INQUIRY, 'Nowe zapytanie');
        $this->createEventForUser($user, Event::STATUS_PENDING_CANCELLATION, 'Do anulacji');

        NotificationService::clearCacheForUser($user->id);

        $response = $this->getJson(route('admin.notifications.counts'));

        $response->assertOk()->assertJson([
            'new_events' => 1,
            'pending_cancellation_events' => 1,
            'invoice_requests' => 0,
        ]);
    }

    public function test_inbox_returns_more_items_than_topbar_defaults(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        for ($i = 1; $i <= 6; $i++) {
            $this->createEventForUser($user, Event::STATUS_INQUIRY, 'Nowa impreza '.$i);
        }

        NotificationService::clearCacheForUser($user->id);

        $topbar = NotificationService::getTopbarDataForUser($user->id);
        $inbox = NotificationService::getInboxDataForUser($user->id, 'new_event');

        $this->assertLessThanOrEqual(4, count($topbar['items_by_type']['new_event']));
        $this->assertGreaterThan(4, count($inbox['items']));
    }

    public function test_topbar_blade_does_not_embed_payload_in_x_data_attribute(): void
    {
        $html = view('filament.components.topbar-notifications', [
            'newTasksCount' => 2,
            'unreadMessagesCount' => 1,
            'commentsCount' => 0,
            'newEventsCount' => 1,
            'confirmedEventsCount' => 0,
            'pendingCancellationEventsCount' => 0,
            'invoiceRequestsCount' => 0,
            'totalUnread' => 3,
            'canSeeInvoiceRequests' => true,
            'notificationItems' => [],
            'notificationItemsByType' => [
                'task' => [[
                    'type' => 'task',
                    'id' => 1,
                    'revision' => '1',
                    'title' => 'Termin raty: Dopłata (90%) (Paryż) "test"',
                    'meta' => 'Termin: 27.08.2026 | Status: Do zrobienia',
                    'time' => '1 godzina temu',
                    'url' => 'http://127.0.0.1:8000/admin/events/19/tasks?editTask=1',
                    'color' => 'violet',
                    'fingerprint' => 'abc',
                    'is_read' => false,
                ]],
            ],
        ])->render();

        $this->assertStringContainsString('id="sor-topbar-notifications-boot"', $html);
        $this->assertStringContainsString('window.sorTopbarNotifications()', $html);
        $this->assertStringContainsString('x-data="window.sorTopbarNotifications()"', $html);
        $this->assertStringContainsString('Dop\\u0142ata', $html);
        $this->assertStringContainsString('Pary\\u017c', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/custom-topbar-notifications[^>]*x-data="\{/',
            $html,
        );
        $this->assertStringNotContainsString('meta[name=\\"csrf-token\\"]', $html);
        $this->assertStringContainsString('meta[name=csrf-token]', $html);
    }

    public function test_confirmed_and_insurance_alerts_have_distinct_fingerprints(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = $this->createEventForUser($user, Event::STATUS_CONFIRMED, 'Paryż ubezpieczenie');

        if (! \Illuminate\Support\Facades\Schema::hasColumn('events', 'insurance_status')) {
            $this->markTestSkipped('Brak kolumny insurance_status.');
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('event_day_insurance')
            && \Illuminate\Support\Facades\Schema::hasTable('insurances')) {
            $insuranceId = \App\Models\Insurance::query()->value('id');

            if (! $insuranceId) {
                $insuranceId = \App\Models\Insurance::query()->create([
                    'name' => 'Test insurance',
                    'price_per_person' => 10,
                    'active' => true,
                ])->id;
            }

            \Illuminate\Support\Facades\DB::table('event_day_insurance')->insert([
                'event_id' => $event->id,
                'day' => 1,
                'insurance_id' => $insuranceId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $this->markTestSkipped('Brak tabel ubezpieczeń.');
        }

        NotificationService::clearCacheForUser($user->id);
        $data = NotificationService::getTopbarDataForUser($user->id, fresh: true);

        $eventItems = collect($data['items_by_type']['event'])
            ->where('id', $event->id)
            ->values();

        $this->assertGreaterThanOrEqual(1, $eventItems->count());

        $fingerprints = $eventItems->pluck('fingerprint')->filter()->values();
        $this->assertSame(
            $fingerprints->count(),
            $fingerprints->unique()->count(),
            'Powiadomienia imprezy i ubezpieczenia nie mogą dzielić fingerprintu (psuje Alpine x-for).',
        );

        $types = $eventItems->pluck('type')->unique()->values();
        if ($types->contains('insurance_alert')) {
            $this->assertTrue($types->contains('event'));
            $this->assertTrue($types->contains('insurance_alert'));
        }
    }
}
