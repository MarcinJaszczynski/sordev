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

    public function test_owner_and_admin_roles_can_view_final_financial_results(): void
    {
        config(['executive.open_access' => false]);

        Role::findOrCreate('super_admin');
        Role::findOrCreate('wlasciciel');
        Role::findOrCreate('admin');
        Role::findOrCreate('biuro');

        $owner = User::factory()->create();
        $owner->assignRole('wlasciciel');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $office = User::factory()->create();
        $office->assignRole('biuro');

        $this->assertTrue(ExecutiveAccess::canViewFinalFinancialResults($owner));
        $this->assertTrue(ExecutiveAccess::canViewFinalFinancialResults($admin));
        $this->assertFalse(ExecutiveAccess::canViewFinalFinancialResults($office));
        $this->assertFalse(ExecutiveAccess::canAccessSensitiveAnalytics($office));
        $this->assertTrue(ExecutiveAccess::canAccessSensitiveAnalytics($admin));
    }

    public function test_closed_settlement_summary_hidden_from_office_staff(): void
    {
        config(['executive.open_access' => false]);

        Role::findOrCreate('biuro');
        $office = User::factory()->create();
        $office->assignRole('biuro');

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->update(['status' => 'closed']);

        $this->actingAs($office);
        $this->assertFalse(ExecutiveAccess::canViewSettlementFinancialSummary($settlement->fresh()));
    }

    public function test_open_access_grants_final_results_to_all_authenticated_users(): void
    {
        config(['executive.open_access' => true]);

        Role::findOrCreate('biuro');
        $office = User::factory()->create();
        $office->assignRole('biuro');

        $this->assertTrue(ExecutiveAccess::canViewFinalFinancialResults($office));
        $this->assertTrue(ExecutiveAccess::canAccessStatisticsPanel($office));
    }

    public function test_active_settlement_summary_visible_to_office_staff(): void
    {
        config(['executive.open_access' => false]);

        Role::findOrCreate('biuro');
        $office = User::factory()->create();
        $office->assignRole('biuro');

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->update(['status' => 'active']);

        $this->actingAs($office);
        $this->assertTrue(ExecutiveAccess::canViewSettlementFinancialSummary($settlement->fresh()));
    }
}
