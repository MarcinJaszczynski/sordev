<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\EditEventProgram;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventWorkflowFinanceRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_event_workflow_finance_changed_increments_refresh_tick(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $event = Event::factory()->create();

        Livewire::test(EditEventProgram::class, ['record' => $event->getKey()])
            ->assertSet('workflowFinanceTick', 0)
            ->dispatch('event-workflow-finance-changed')
            ->assertSet('workflowFinanceTick', 1);
    }
}
