<?php

namespace Tests\Feature;

use App\Filament\Pages\OperationsCalendarPage;
use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Filament\Resources\TaskResource\Pages\TasksKanbanBoardPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regresja: header "Dodaj zadanie" musi montować cache'owaną akcję createTask
 * przez replaceMountedAction — zwykły mountAction() w callbacku innej Action
 * czyści stos i modal nie wychodzi.
 */
class OperationsCalendarCreateTaskModalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
    }

    public function test_calendar_header_open_create_task_mounts_create_task_action(): void
    {
        Livewire::actingAs($this->user)
            ->test(OperationsCalendarPage::class)
            ->call('mountAction', 'openCreateTask')
            ->assertSet('mountedActions', ['createTask']);
    }

    public function test_calendar_date_click_opens_create_task_modal(): void
    {
        Livewire::actingAs($this->user)
            ->test(OperationsCalendarPage::class)
            ->call('openCreateTaskModalForDate', '2026-09-15')
            ->assertSet('clickedDate', '2026-09-15')
            ->assertSet('mountedActions', ['createTask']);
    }

    public function test_list_tasks_header_open_create_task_mounts_create_task_action(): void
    {
        Livewire::actingAs($this->user)
            ->test(ListTasks::class)
            ->call('mountAction', 'openCreateTask')
            ->assertSet('mountedActions', ['createTask']);
    }

    public function test_kanban_header_open_create_task_mounts_create_task_action(): void
    {
        Livewire::actingAs($this->user)
            ->test(TasksKanbanBoardPage::class)
            ->call('mountAction', 'openCreateTask')
            ->assertSet('mountedActions', ['createTask']);
    }
}
