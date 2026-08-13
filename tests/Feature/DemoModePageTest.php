<?php

namespace Tests\Feature;

use App\Filament\Pages\DemoModePage;
use App\Support\DemoMailMode;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DemoModePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        config(['mail.demo_to' => null]);
    }

    public function test_admin_can_enable_demo_mode_with_email(): void
    {
        if (! Schema::hasTable('app_settings')) {
            $this->markTestSkipped('Brak tabeli app_settings.');
        }

        $user = User::factory()->create();
        $user->assignRole('admin');

        Filament::setServingStatus(true);
        $this->actingAs($user);

        Livewire::test(DemoModePage::class)
            ->fillForm([
                'enabled' => true,
                'email' => 'demo.target@example.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(DemoMailMode::isEnabled());
        $this->assertSame('demo.target@example.com', DemoMailMode::redirectTo());
        $this->assertSame('panel', DemoMailMode::status()['source']);
    }

    public function test_disabling_in_panel_overrides_env_fallback(): void
    {
        if (! Schema::hasTable('app_settings')) {
            $this->markTestSkipped('Brak tabeli app_settings.');
        }

        config(['mail.demo_to' => 'from.env@example.com']);

        DemoMailMode::save(false, 'ignored@example.com');

        $this->assertFalse(DemoMailMode::isEnabled());
        $this->assertNull(DemoMailMode::redirectTo());
        $this->assertSame('panel', DemoMailMode::status()['source']);
    }
}
