<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventProgramPoint;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventProgramPdfViewTest extends TestCase
{
    public function test_program_pdf_with_times_shows_hours_column(): void
    {
        $event = new Event([
            'id' => 10,
            'name' => 'Wycieczka testowa',
            'code' => 'EVT-10',
            'start_date' => Carbon::parse('2026-06-01'),
            'end_date' => Carbon::parse('2026-06-03'),
        ]);

        $point = new EventProgramPoint([
            'name' => 'Zwiedzanie muzeum',
            'description' => '<p>Opis punktu</p>',
            'day' => 1,
            'order' => 1,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'include_in_program' => true,
        ]);

        $html = view('pdf.packages.program', $this->viewData($event, collect([1 => collect([$point])]), true))->render();

        $this->assertStringContainsString('Program imprezy', $html);
        $this->assertStringContainsString('Wycieczka testowa', $html);
        $this->assertStringContainsString('Godziny', $html);
        $this->assertStringContainsString('09:00', $html);
        $this->assertStringContainsString('11:00', $html);
        $this->assertStringContainsString('Zwiedzanie muzeum', $html);
        $this->assertStringContainsString('Opis punktu', $html);
    }

    public function test_program_pdf_without_times_hides_hours_column(): void
    {
        $event = new Event([
            'id' => 10,
            'name' => 'Wycieczka testowa',
            'start_date' => Carbon::parse('2026-06-01'),
        ]);

        $point = new EventProgramPoint([
            'name' => 'Obiad',
            'description' => 'Restauracja',
            'day' => 1,
            'order' => 1,
            'start_time' => '12:00',
            'end_time' => '13:00',
            'include_in_program' => true,
        ]);

        $html = view('pdf.packages.program', $this->viewData($event, collect([1 => collect([$point])]), false))->render();

        $this->assertStringContainsString('Program imprezy', $html);
        $this->assertStringContainsString('Obiad', $html);
        $this->assertStringContainsString('Restauracja', $html);
        $this->assertStringNotContainsString('>Godziny<', $html);
        $this->assertStringNotContainsString('12:00', $html);
    }

    public function test_program_pdf_empty_state(): void
    {
        $event = new Event([
            'id' => 1,
            'name' => 'Pusta impreza',
            'start_date' => Carbon::parse('2026-06-01'),
        ]);

        $html = view('pdf.packages.program', $this->viewData($event, collect(), true))->render();

        $this->assertStringContainsString('Brak punktów programu.', $html);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, EventProgramPoint>>  $programByDay
     * @return array<string, mixed>
     */
    private function viewData(Event $event, $programByDay, bool $showTimes): array
    {
        return [
            'audienceLabel' => $showTimes
                ? 'Program imprezy (z godzinami)'
                : 'Program imprezy (bez godzin)',
            'event' => $event,
            'company' => ['name' => 'BP Rafa'],
            'logoDataUri' => null,
            'generatedAt' => Carbon::parse('2026-06-01 10:00:00'),
            'programByDay' => $programByDay,
            'showTimes' => $showTimes,
        ];
    }
}
