<?php

namespace Tests\Unit\Support\Tasks;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Support\Tasks\TaskCommentPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCommentPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_own_and_other_variants(): void
    {
        $viewer = User::factory()->create(['name' => 'Rafa']);
        $other = User::factory()->create(['name' => 'Anna']);
        $task = Task::factory()->create();

        $own = TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $viewer->id,
            'content' => 'Moja wiadomość',
        ]);
        $own->setRelation('author', $viewer);

        $foreign = TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $other->id,
            'content' => 'Cudza wiadomość',
        ]);
        $foreign->setRelation('author', $other);

        $this->assertSame(
            TaskCommentPresentation::VARIANT_OWN,
            TaskCommentPresentation::for($own, $viewer->id)['variant'],
        );
        $this->assertSame(
            TaskCommentPresentation::VARIANT_OTHER,
            TaskCommentPresentation::for($foreign, $viewer->id)['variant'],
        );
    }

    public function test_reservation_card_is_detected_and_parsed(): void
    {
        $user = User::factory()->create(['name' => 'Rafa']);
        $task = Task::factory()->create();

        $comment = TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => "Rezerwacja: Hotel Galon\nHotel: Hotel Galon\nCena: 420 PLN\nWarunki: HB\nŚwiadczenia: śniadanie + obiadokolacja\nDodatkowa uwaga dla biura",
        ]);
        $comment->setRelation('author', $user);

        $presentation = TaskCommentPresentation::for($comment, $user->id);

        $this->assertSame(TaskCommentPresentation::VARIANT_RESERVATION, $presentation['variant']);
        $this->assertSame('Rezerwacja: Hotel Galon', $presentation['reservation']['title']);
        $this->assertSame(
            [
                ['label' => 'Hotel', 'value' => 'Hotel Galon'],
                ['label' => 'Cena', 'value' => '420 PLN'],
                ['label' => 'Warunki', 'value' => 'HB'],
                ['label' => 'Świadczenia', 'value' => 'śniadanie + obiadokolacja'],
            ],
            $presentation['reservation']['fields'],
        );
        $this->assertSame('Dodatkowa uwaga dla biura', $presentation['reservation']['remainder']);
    }

    public function test_system_variant_for_payment_reminder_marker(): void
    {
        $user = User::factory()->create(['name' => 'Rafa']);
        $task = Task::factory()->create();

        $comment = TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => "Kwota: 100 PLN\n[payment-reminder:settlement_cost:1:plan]",
        ]);
        $comment->setRelation('author', $user);

        $this->assertSame(
            TaskCommentPresentation::VARIANT_SYSTEM,
            TaskCommentPresentation::for($comment, $user->id)['variant'],
        );
    }

    public function test_plain_multiline_text_is_not_reservation(): void
    {
        $this->assertFalse(
            TaskCommentPresentation::isReservationLike("Cześć\nproszę sprawdzić hotel"),
        );
    }

    public function test_list_bubble_flattens_reservation_to_chat_variant(): void
    {
        $viewer = User::factory()->create(['name' => 'Rafa']);
        $task = Task::factory()->create();

        $comment = TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $viewer->id,
            'content' => "Rezerwacja: Hotel Galon\nHotel: Hotel Galon\nCena: 420 PLN",
        ]);
        $comment->setRelation('author', $viewer);

        $bubble = TaskCommentPresentation::forListBubble($comment, $viewer->id);

        $this->assertSame(TaskCommentPresentation::VARIANT_OWN, $bubble['variant']);
        $this->assertSame('Rafa', $bubble['author']);
        $this->assertStringContainsString('Hotel Galon', $bubble['content']);
        $this->assertArrayNotHasKey('reservation', $bubble);
    }
}
