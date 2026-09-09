<?php

namespace Tests\Feature;

use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Livewire\TaskFullEditor;
use App\Models\Task;
use App\Models\TaskAttachment;
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

    public function test_selecting_task_opens_side_editor_with_discussion(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $task = Task::create([
            'title' => 'Zadanie z wieloma komentarzami',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => 'Starszy komentarz',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => 'Najnowszy komentarz',
        ]);

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->call('selectTask', $task->id)
            ->assertSet('selectedTaskId', $task->id)
            ->assertSee('Dyskusja')
            ->assertSee('Najnowszy komentarz')
            ->assertSee('Wyślij');
    }

    public function test_side_editor_comment_thread_collapses_after_reply(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $task = Task::create([
            'title' => 'Zadanie do odpowiedzi',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => 'Pierwszy',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        Livewire::actingAs($user)
            ->test(TaskFullEditor::class, ['taskId' => $task->id])
            ->call('toggleEarlierComments')
            ->assertSet('showEarlierComments', true)
            ->set('newCommentContent', 'Nowa odpowiedź z panelu')
            ->call('addComment')
            ->assertSet('showEarlierComments', false)
            ->assertSet('newCommentContent', '')
            ->assertSee('Nowa odpowiedź z panelu');

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => 'Nowa odpowiedź z panelu',
        ]);
    }

    public function test_list_row_shows_latest_comment_and_attachment(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->assignRole('admin');

        $task = Task::create([
            'title' => 'Zadanie z plikiem i komentarzem',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        $path = UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf')->store('task-attachments', 'public');

        TaskAttachment::query()->create([
            'task_id' => $task->id,
            'name' => 'brief.pdf',
            'file_path' => $path,
            'user_id' => $user->id,
        ]);

        TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => 'Podgląd ostatniej wiadomości na liście',
        ]);

        $component = Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->assertSee('Dyskusja')
            ->assertSee('Podgląd ostatniej wiadomości na liście')
            ->assertSee('brief.pdf')
            ->assertSee('Załączniki')
            ->assertSeeHtml('admin/task-attachments/')
            ->set('listSort', 'title_asc')
            ->assertSet('listSort', 'title_asc')
            ->assertSee('Zadanie z plikiem i komentarzem');

        $this->assertStringContainsString('target="_blank"', $component->html());
    }

    public function test_selected_task_is_pinned_to_top_of_list(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $older = Task::create([
            'title' => 'Stare zadanie na dole',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'updated_at' => now()->subDays(10),
            'created_at' => now()->subDays(10),
        ]);

        $newer = Task::create([
            'title' => 'Nowe zadanie na górze',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $component = Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->call('selectTask', $older->id)
            ->assertSet('selectedTaskId', $older->id);

        $html = $component->html();
        $olderPos = strpos($html, 'Stare zadanie na dole');
        $newerPos = strpos($html, 'Nowe zadanie na górze');

        $this->assertNotFalse($olderPos);
        $this->assertNotFalse($newerPos);
        $this->assertLessThan($newerPos, $olderPos, 'Wybrane zadanie powinno być wyżej na liście niż nowsze nieotwarte.');
    }
}
