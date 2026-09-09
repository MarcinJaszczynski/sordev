<?php

namespace Tests\Unit\Support\Tasks;

use App\Models\TaskStatus;
use App\Support\Tasks\TaskStatusPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskStatusPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_maps_seeded_status_names_to_mockup_tones(): void
    {
        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        $cases = [
            'Do zrobienia' => TaskStatusPresentation::TONE_TODO,
            'W trakcie' => TaskStatusPresentation::TONE_PROGRESS,
            'Oczekuje na weryfikację' => TaskStatusPresentation::TONE_PROGRESS,
            'Zaakceptowane' => TaskStatusPresentation::TONE_DONE,
            'Zakończone' => TaskStatusPresentation::TONE_DONE,
            'Anulowane' => TaskStatusPresentation::TONE_CANCEL,
            'Zarchiwizowane' => TaskStatusPresentation::TONE_DONE,
        ];

        foreach ($cases as $name => $tone) {
            $status = TaskStatus::query()->where('name', $name)->first();
            $this->assertNotNull($status, $name);
            $this->assertSame($tone, TaskStatusPresentation::toneForStatus($status), $name);
            $this->assertStringContainsString(
                'task-status-pill--'.$tone,
                TaskStatusPresentation::pillClass($status),
            );
        }
    }

    public function test_initials_from_name(): void
    {
        $this->assertSame('MJ', TaskStatusPresentation::initials('Marcin Jaszczyński'));
        $this->assertSame('A', TaskStatusPresentation::initials('Anna'));
        $this->assertSame('?', TaskStatusPresentation::initials(''));
    }
}
