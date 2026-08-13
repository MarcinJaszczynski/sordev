<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\EventFinance;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceRootProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_finance_has_single_livewire_root_with_settlement(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $event = Event::factory()->create(['status' => Event::STATUS_CONFIRMED]);

        if (Schema::hasTable('event_settlements')) {
            $payload = ['event_id' => $event->id];
            if (Schema::hasColumn('event_settlements', 'status')) {
                $payload['status'] = 'draft';
            }
            if (Schema::hasColumn('event_settlements', 'name')) {
                $payload['name'] = 'Test settlement';
            }
            EventSettlement::query()->create($payload);
        }

        Livewire::test(EventFinance::class, ['record' => $event->getKey()])
            ->assertSuccessful();
    }
}
