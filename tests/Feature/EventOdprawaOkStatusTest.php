<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventOdprawaOkStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_odprawa_ok_is_a_status_option_and_confirmed_like(): void
    {
        $options = Event::getStatusOptions();

        $this->assertArrayHasKey(Event::STATUS_ODPRAWA_OK, $options);
        $this->assertSame('Odprawa OK', $options[Event::STATUS_ODPRAWA_OK]);
        $this->assertSame(
            [Event::STATUS_CONFIRMED, Event::STATUS_ODPRAWA_OK],
            Event::getConfirmedLikeStatuses(),
        );
    }

    public function test_change_status_to_odprawa_ok_marks_check_in_completed(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'check_in_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $event->changeStatus(Event::STATUS_ODPRAWA_OK);
        $event->refresh();

        $this->assertSame(Event::STATUS_ODPRAWA_OK, $event->status);
        $this->assertSame('completed', $event->check_in_status);
        $this->assertTrue($event->isConfirmedLike());
        $this->assertTrue($event->isCheckInCompleted());
    }

    public function test_confirmed_like_filter_includes_odprawa_ok(): void
    {
        $confirmed = Event::factory()->create(['status' => Event::STATUS_CONFIRMED]);
        $odprawa = Event::factory()->create(['status' => Event::STATUS_ODPRAWA_OK]);
        Event::factory()->create(['status' => Event::STATUS_OFFER]);

        $ids = Event::query()
            ->whereIn('status', Event::getConfirmedLikeStatuses())
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing([$confirmed->id, $odprawa->id], $ids);
    }
}
