<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventTemplateResource;
use App\Filament\Resources\EventTemplateResource\Pages\EditEventTemplate;
use App\Filament\Resources\EventTemplateResource\Pages\EditEventTemplateProgram;
use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventTemplateAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'view event_template',
            'edit event_template',
            'edit event_template_program',
            'create event',
            'edit event',
            'view event',
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'biuro', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'programista', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_biuro_can_view_template_but_needs_unlock_to_edit(): void
    {
        $user = User::factory()->create();
        $user->assignRole('biuro');
        $user->syncPermissions([
            'view event_template', 'view event', 'create event', 'edit event',
        ]);

        $template = EventTemplate::factory()->create(['name' => 'Szablon biura']);

        $this->actingAs($user);

        $this->assertTrue(EventResource::canCreate());
        $this->assertTrue(EventTemplateResource::canViewAny());
        $this->assertTrue(EditEventTemplate::canAccess());
        $this->assertTrue(EditEventTemplateProgram::canAccess());

        $component = Livewire::test(EditEventTemplate::class, ['record' => $template->getKey()]);

        $component->assertOk()
            ->assertSet('templateEditingUnlocked', false)
            ->assertSee('Podgląd szablonu')
            ->assertDontSee('Zapisz zmiany');

        $component->call('unlockTemplateEditing')
            ->assertSet('templateEditingUnlocked', true)
            ->assertSee('Zapisz zmiany');
    }

    public function test_admin_edits_template_without_unlock(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $template = EventTemplate::factory()->create(['name' => 'Szablon admina']);

        $this->actingAs($user);

        Livewire::test(EditEventTemplate::class, ['record' => $template->getKey()])
            ->assertOk()
            ->assertSet('templateEditingUnlocked', true)
            ->assertSee('Zapisz zmiany');
    }

    public function test_programista_can_access_program_page_only_for_full_edit_gate(): void
    {
        $user = User::factory()->create();
        $user->assignRole('programista');
        $user->syncPermissions(['view event_template', 'edit event_template_program']);

        $this->actingAs($user);

        $this->assertTrue(EventTemplateResource::canViewAny());
        $this->assertTrue(EditEventTemplateProgram::canAccess());
        $this->assertTrue(EditEventTemplate::canAccess());
    }

    public function test_view_event_template_permission_allows_template_list(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view event_template');

        $this->actingAs($user);

        $this->assertTrue(EventTemplateResource::canViewAny());
        $this->assertTrue(EditEventTemplate::canAccess());
    }
}
