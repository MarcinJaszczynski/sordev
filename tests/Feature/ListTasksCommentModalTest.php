<?php

namespace Tests\Feature;

use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ListTasksCommentModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_add_comment_modal_saves_comment_for_task(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $task = Task::create([
            'title' => 'Zadanie z komentarzem z listy',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->call('openAddCommentModal', $task->id)
            ->assertSet('mountedActions', ['addComment'])
            ->assertSet('commentingTaskId', $task->id)
            ->callAction('addComment', data: [
                'content' => 'Komentarz dodany z listy zadań.',
            ])
            ->assertHasNoActionErrors()
            ->assertSet('mountedActions', [])
            ->assertSet('commentingTaskId', null);

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => 'Komentarz dodany z listy zadań.',
        ]);
    }

    public function test_add_attachment_modal_saves_files_for_task(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->assignRole('admin');

        $task = Task::create([
            'title' => 'Zadanie z załącznikiem z listy',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        $path = UploadedFile::fake()->create('notatka.pdf', 20, 'application/pdf')->store('task-attachments', 'public');

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->call('openAddAttachmentModal', $task->id)
            ->callAction('addAttachment', data: [
                'files' => [$path],
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('task_attachments', [
            'task_id' => $task->id,
            'file_path' => $path,
        ]);
    }

    public function test_task_list_can_expand_all_comments(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $task = Task::create([
            'title' => 'Zadanie z wieloma komentarzami',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => 'Najnowszy komentarz',
        ]);

        TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => 'Starszy komentarz',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->call('toggleExpandedComments', $task->id)
            ->assertSet('expandedCommentsTaskId', $task->id)
            ->assertSee('Najnowszy komentarz')
            ->assertSee('Starszy komentarz')
            ->call('toggleExpandedComments', $task->id)
            ->assertSet('expandedCommentsTaskId', null);
    }
}
