<?php

namespace Tests\Feature;

use App\Enums\TaskSource;
use App\Livewire\PilotEventChecklist;
use App\Models\ChecklistTemplate;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Task;
use App\Models\User;
use App\Services\PilotChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotChecklistTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
        $this->seed(\Database\Seeders\ChecklistTemplateSeeder::class);
    }

    public function test_apply_template_creates_tasks_for_event(): void
    {
        [$event, $user] = $this->makeEventAndUser();
        $template = ChecklistTemplate::query()->where('name', 'Wyjazd krajowy')->firstOrFail();

        $added = app(PilotChecklistService::class)->applyTemplate($event, $template, $user);

        $this->assertSame($template->items()->count(), $added);
        $this->assertSame($template->items()->count(), Task::query()
            ->where('taskable_type', Event::class)
            ->where('taskable_id', $event->id)
            ->where('source', TaskSource::PilotChecklist->value)
            ->count());
    }

    public function test_checklist_tasks_are_excluded_from_office_task_list(): void
    {
        [$event, $user] = $this->makeEventAndUser();
        $template = ChecklistTemplate::query()->where('name', 'Wyjazd krajowy')->firstOrFail();

        app(PilotChecklistService::class)->applyTemplate($event, $template, $user);

        $this->assertGreaterThan(0, Task::query()->pilotChecklistOnly()->count());
        $this->assertSame(0, Task::query()->officeOnly()->count());
    }

    public function test_apply_template_skips_duplicates(): void
    {
        [$event, $user] = $this->makeEventAndUser();
        $template = ChecklistTemplate::query()->where('name', 'Wyjazd krajowy')->firstOrFail();
        $service = app(PilotChecklistService::class);

        $service->applyTemplate($event, $template, $user);
        $secondRun = $service->applyTemplate($event, $template, $user);

        $this->assertSame(0, $secondRun);
        $this->assertSame($template->items()->count(), Task::query()
            ->where('taskable_id', $event->id)
            ->where('taskable_type', Event::class)
            ->count());
    }

    public function test_templates_can_be_combined(): void
    {
        [$event, $user] = $this->makeEventAndUser();
        $service = app(PilotChecklistService::class);

        $krajowy = ChecklistTemplate::query()->where('name', 'Wyjazd krajowy')->firstOrFail();
        $jednodniowy = ChecklistTemplate::query()->where('name', 'Wycieczka jednodniowa')->firstOrFail();

        $service->applyTemplate($event, $krajowy, $user);
        $service->applyTemplate($event, $jednodniowy, $user);

        $total = Task::query()
            ->where('taskable_id', $event->id)
            ->where('taskable_type', Event::class)
            ->count();

        // Combined unique items (some overlap is deduplicated by title).
        $this->assertGreaterThan($krajowy->items()->count(), $total);
    }

    public function test_apply_template_via_livewire_creates_tasks_for_event(): void
    {
        [$event, $user] = $this->makeEventAndUser();
        Role::firstOrCreate(['name' => 'biuro', 'guard_name' => 'web']);
        $user->assignRole('biuro');

        $template = ChecklistTemplate::query()->where('name', 'Wyjazd krajowy')->firstOrFail();

        Livewire::actingAs($user)
            ->test(PilotEventChecklist::class, ['eventId' => $event->id])
            ->set('selectedTemplateId', $template->id)
            ->call('applyTemplate')
            ->assertNotified();

        $this->assertSame($template->items()->count(), Task::query()
            ->where('taskable_type', Event::class)
            ->where('taskable_id', $event->id)
            ->count());
    }

    public function test_office_user_sees_create_template_prompt_when_no_templates(): void
    {
        ChecklistTemplate::query()->delete();

        [$event, $user] = $this->makeEventAndUser();
        Role::firstOrCreate(['name' => 'biuro', 'guard_name' => 'web']);
        $user->assignRole('biuro');

        Livewire::actingAs($user)
            ->test(PilotEventChecklist::class, ['eventId' => $event->id])
            ->assertSee('Brak aktywnych szablonów checklisty')
            ->assertSee('Utwórz pierwszy szablon');
    }

    /**
     * @return array{0: Event, 1: User}
     */
    private function makeEventAndUser(): array
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Wycieczka testowa',
            'client_name' => 'Szkoła Testowa',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-05',
            'participant_count' => 30,
            'status' => 'confirmed',
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        return [$event, $user];
    }
}
