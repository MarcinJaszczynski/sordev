<?php

namespace Tests\Unit\Support;

use App\Enums\TaskSource;
use App\Filament\Pilot\Pages\PilotChecklistPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\TaskResource;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Support\Tasks\TaskNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_checklist_task_links_to_admin_edit_page(): void
    {
        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        $user = User::factory()->create();
        $event = Event::factory()->create();
        $task = Task::create([
            'title' => 'Potwierdzić autokar',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'source' => TaskSource::PilotChecklist->value,
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
        ]);

        $this->assertSame(
            TaskResource::getUrl('edit', ['record' => $task->id]),
            TaskNavigation::editUrl($task),
        );
        $this->assertSame(
            PilotChecklistPage::urlFor($event),
            TaskNavigation::pilotWorkUrl($task),
        );
    }

    public function test_office_task_links_to_admin_edit_page(): void
    {
        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        $user = User::factory()->create();
        $task = Task::create([
            'title' => 'Zadanie biurowe',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'source' => TaskSource::Office->value,
        ]);

        $this->assertSame(
            TaskResource::getUrl('edit', ['record' => $task->id]),
            TaskNavigation::editUrl($task),
        );
        $this->assertNull(TaskNavigation::pilotWorkUrl($task));
    }

    public function test_full_view_url_opens_task_modal_on_list_for_office_task(): void
    {
        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        $user = User::factory()->create();
        $task = Task::create([
            'title' => 'Zadanie biurowe',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'source' => TaskSource::Office->value,
        ]);

        $url = TaskNavigation::fullViewUrl($task);

        $this->assertStringContainsString(TaskResource::getUrl('index'), $url);
        $this->assertStringContainsString('editTask='.$task->id, $url);
    }

    public function test_full_view_url_opens_event_tasks_with_modal_for_event_task(): void
    {
        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        $user = User::factory()->create();
        $event = Event::factory()->create();
        $task = Task::create([
            'title' => 'Zadanie imprezy',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'source' => TaskSource::Office->value,
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
        ]);

        $url = TaskNavigation::fullViewUrl($task, TaskNavigation::commentsRelationManagerIndex());

        $this->assertStringContainsString(EventResource::getUrl('tasks', ['record' => $event->id]), $url);
        $this->assertStringContainsString('editTask='.$task->id, $url);
        $this->assertStringContainsString('activeRelationManager=0', $url);
    }
}
