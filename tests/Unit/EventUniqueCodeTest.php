<?php

namespace Tests\Unit;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventUniqueCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_unique_code_is_eight_alphanumeric_characters(): void
    {
        $code = Event::generateUniqueCode(Carbon::parse('2026-07-01'));

        $this->assertSame(8, strlen($code));
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{8}$/', $code);
        $this->assertStringStartsWith('26', $code);
    }

    public function test_event_auto_assigns_code_on_create(): void
    {
        $event = Event::factory()->create(['code' => null]);

        $this->assertNotNull($event->code);
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{8}$/', $event->code);
    }

    public function test_generate_unique_code_avoids_collisions(): void
    {
        Event::factory()->create(['code' => '26ABCDEF']);

        $code = Event::generateUniqueCode(Carbon::parse('2026-07-01'));

        $this->assertNotSame('26ABCDEF', $code);
    }
}
