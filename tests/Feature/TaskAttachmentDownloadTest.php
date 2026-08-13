<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TaskAttachmentDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Storage::fake('public');
    }

    public function test_assignee_can_download_task_attachment(): void
    {
        $author = User::factory()->create();
        $author->assignRole('admin');
        $assignee = User::factory()->create();
        $assignee->assignRole('admin');

        $task = Task::create([
            'title' => 'Zadanie z plikiem',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $author->id,
            'assignee_id' => $assignee->id,
        ]);

        Storage::disk('public')->put('task-attachments/test.txt', 'hello');

        $attachment = TaskAttachment::create([
            'task_id' => $task->id,
            'user_id' => $author->id,
            'name' => 'test.txt',
            'file_path' => 'task-attachments/test.txt',
            'mime_type' => 'text/plain',
            'size' => 5,
        ]);

        $this->actingAs($assignee)
            ->get(route('admin.task-attachments.download', ['attachment' => $attachment->id]))
            ->assertOk();
    }

    public function test_unrelated_user_cannot_download_task_attachment(): void
    {
        $author = User::factory()->create();
        $stranger = User::factory()->create();

        $task = Task::create([
            'title' => 'Zadanie z plikiem',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $author->id,
        ]);

        Storage::disk('public')->put('task-attachments/secret.txt', 'secret');

        $attachment = TaskAttachment::create([
            'task_id' => $task->id,
            'user_id' => $author->id,
            'name' => 'secret.txt',
            'file_path' => 'task-attachments/secret.txt',
            'mime_type' => 'text/plain',
            'size' => 6,
        ]);

        $this->actingAs($stranger)
            ->get(route('admin.task-attachments.download', ['attachment' => $attachment->id]))
            ->assertForbidden();
    }
}
