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

    public function test_normalize_code_uppercases_and_strips_spaces(): void
    {
        $this->assertSame('ZP.271.12.2026', Event::normalizeCode(' zp.271.12.2026 '));
        $this->assertSame('UM/GMINA-1', Event::normalizeCode('um/gmina-1'));
        $this->assertNull(Event::normalizeCode('   '));
        $this->assertNull(Event::normalizeCode(null));
    }

    public function test_create_keeps_manually_provided_code(): void
    {
        $event = Event::factory()->create(['code' => 'zp.271.12.2026']);

        $this->assertSame('ZP.271.12.2026', $event->code);
    }

    public function test_update_does_not_clear_existing_code(): void
    {
        $event = Event::factory()->create(['code' => '26ABCDEF']);

        $event->update(['code' => '']);

        $this->assertSame('26ABCDEF', $event->fresh()->code);
    }
}
