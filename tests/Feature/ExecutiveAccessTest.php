<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\User;
use App\Support\ExecutiveAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExecutiveAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_roles_can_view_final_financial_results(): void
    {
        config(['executive.open_access' => false]);

        Role::findOrCreate('super_admin');
        Role::findOrCreate('wlasciciel');
        Role::findOrCreate('admin');

        $owner = User::factory()->create();
        $owner->assignRole('wlasciciel');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue(ExecutiveAccess::canViewFinalFinancialResults($owner));
        $this->assertFalse(ExecutiveAccess::canViewFinalFinancialResults($admin));
    }

    public function test_closed_settlement_summary_hidden_from_regular_staff(): void
    {
        config(['executive.open_access' => false]);

        Role::findOrCreate('admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->update(['status' => 'closed']);

        $this->actingAs($admin);
        $this->assertFalse(ExecutiveAccess::canViewSettlementFinancialSummary($settlement->fresh()));
    }

    public function test_open_access_grants_final_results_to_all_authenticated_users(): void
    {
        config(['executive.open_access' => true]);

        Role::findOrCreate('admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue(ExecutiveAccess::canViewFinalFinancialResults($admin));
        $this->assertTrue(ExecutiveAccess::canAccessStatisticsPanel($admin));
    }

    public function test_active_settlement_summary_visible_to_regular_staff(): void
    {
        config(['executive.open_access' => false]);

        Role::findOrCreate('admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->update(['status' => 'active']);

        $this->actingAs($admin);
        $this->assertTrue(ExecutiveAccess::canViewSettlementFinancialSummary($settlement->fresh()));
    }
}
