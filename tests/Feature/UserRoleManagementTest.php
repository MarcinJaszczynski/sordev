<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UserRoleManagement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('pilot');
        Role::findOrCreate('admin');
        Role::findOrCreate('biuro');
    }

    public function test_non_admin_cannot_manage_roles_and_permissions(): void
    {
        $biuro = User::factory()->create(['status' => 'active']);
        $biuro->assignRole('biuro');

        $this->assertFalse(UserRoleManagement::canManageRolesAndPermissions($biuro));
    }

    public function test_admin_can_manage_roles_and_permissions(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $this->assertTrue(UserRoleManagement::canManageRolesAndPermissions($admin));
    }

    public function test_ensure_pilot_role_strips_extra_roles_for_restricted_users(): void
    {
        $user = User::factory()->create(['type' => 'user', 'status' => 'active']);
        $user->syncRoles(['pilot', 'biuro']);

        UserRoleManagement::ensurePilotRole($user->fresh());

        $user->refresh();

        $this->assertTrue($user->hasRole('pilot'));
        $this->assertFalse($user->hasRole('biuro'));
        $this->assertSame('pilot', $user->type);
    }

    public function test_is_pilot_only_user(): void
    {
        $pilot = User::factory()->create();
        $pilot->syncRoles(['pilot']);

        $mixed = User::factory()->create();
        $mixed->syncRoles(['pilot', 'admin']);

        $this->assertTrue(UserRoleManagement::isPilotOnlyUser($pilot));
        $this->assertFalse(UserRoleManagement::isPilotOnlyUser($mixed));
    }
}
