<?php

namespace Tests\Unit\Support\Tasks;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Support\Tasks\TaskListColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskListColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanitize_task_text_removes_payment_reminder_marker_and_link(): void
    {
        $text = "Kwota: 100 PLN.\n\n[payment-reminder:settlement_cost:752:plan]\n\nLink: http://127.0.0.1:8000/admin/event-settlements/3/edit";

        $sanitized = TaskListColumn::sanitizeTaskText($text, 512);

        $this->assertStringContainsString('Kwota: 100 PLN.', $sanitized);
        $this->assertStringNotContainsString('[payment-reminder:', $sanitized);
        $this->assertStringNotContainsString('Link: http', $sanitized);
    }

    public function test_sanitize_task_text_trims_leading_indentation(): void
    {
        $text = "   Pierwsza linia\n   Druga linia";

        $this->assertSame("Pierwsza linia\nDruga linia", TaskListColumn::sanitizeTaskText($text, 512));
    }

    public function test_author_label_returns_system_for_system_tasks(): void
    {
        $task = Task::factory()->create([
            'source' => \App\Enums\TaskSource::System->value,
            'author_id' => User::factory()->create()->id,
        ]);

        $this->assertSame('System', TaskListColumn::authorLabel($task));
    }

    public function test_ownership_line_shows_from_author_to_assignee(): void
    {
        $author = User::factory()->create(['name' => 'Anna Autor']);
        $assignee = User::factory()->create(['name' => 'Jan Wykonawca']);

        $task = Task::factory()->create([
            'source' => \App\Enums\TaskSource::Office->value,
            'author_id' => $author->id,
            'assignee_id' => $assignee->id,
        ]);

        $this->assertSame('Jan Wykonawca', TaskListColumn::assigneeLabel($task));
        $this->assertSame('Od Anna Autor dla Jan Wykonawca', TaskListColumn::ownershipLine($task));
    }

    public function test_ownership_line_uses_system_author_and_dash_when_unassigned(): void
    {
        $task = Task::factory()->create([
            'source' => \App\Enums\TaskSource::System->value,
            'author_id' => User::factory()->create(['name' => 'Ukryty Autor'])->id,
            'assignee_id' => null,
        ]);

        $this->assertSame('Od System dla —', TaskListColumn::ownershipLine($task));
    }

    public function test_task_cell_contains_title_and_sanitized_description(): void
    {
        $task = Task::factory()->create([
            'title' => 'Przygotuj ofertę',
            'description' => '<p>Szczegółowy opis</p>[payment-reminder:settlement_cost:1:plan]',
        ]);

        $html = TaskListColumn::taskCellHtml($task);

        $this->assertStringContainsString('Przygotuj ofertę', $html);
        $this->assertStringContainsString('Szczegółowy opis', $html);
        $this->assertStringNotContainsString('[payment-reminder:', $html);
        $this->assertStringContainsString('Brak komentarzy', $html);
    }

    public function test_task_cell_shows_latest_comment_with_author_and_date(): void
    {
        $user = User::factory()->create(['name' => 'Anna Kowalska']);
        $task = Task::factory()->create(['title' => 'Zadanie z komentarzem']);
        $comment = TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => 'To jest ostatni komentarz.',
        ]);

        $task->setRelation('comments', collect([$comment]));
        $comment->setRelation('author', $user);

        $html = TaskListColumn::taskCellHtml($task);

        $this->assertStringContainsString('Ostatni komentarz', $html);
        $this->assertStringContainsString('Anna Kowalska', $html);
        $this->assertStringContainsString('To jest ostatni komentarz.', $html);
    }

    public function test_effective_modified_at_falls_back_to_created_at(): void
    {
        $task = Task::factory()->create();
        $task->updated_at = $task->created_at;

        $this->assertTrue(
            TaskListColumn::effectiveModifiedAt($task)?->equalTo($task->created_at)
        );
    }

    public function test_attachments_cell_renders_download_links(): void
    {
        $task = Task::factory()->create();
        $attachment = $task->attachments()->create([
            'name' => 'umowa.pdf',
            'file_path' => 'task-attachments/umowa.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'user_id' => User::factory()->create()->id,
        ]);

        $task->setRelation('attachments', collect([$attachment]));

        $html = TaskListColumn::attachmentsCellHtml($task);

        $this->assertStringContainsString('umowa.pdf', $html);
        $this->assertStringContainsString($attachment->preview_url, $html);
        $this->assertStringNotContainsString('download=1', $html);
    }

    public function test_due_date_is_red_when_overdue_and_open(): void
    {
        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        $task = Task::factory()->create([
            'due_date' => now()->subDay(),
            'status_id' => Task::getDefaultStatusId(),
        ]);
        $task->load('status');

        $html = TaskListColumn::duePriorityCellHtml($task);

        $this->assertTrue(TaskListColumn::isDueOverdue($task));
        $this->assertStringContainsString('#dc2626', $html);
    }
}
