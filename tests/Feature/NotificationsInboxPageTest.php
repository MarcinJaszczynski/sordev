<?php

namespace Tests\Feature;

use App\Filament\Pages\NotificationsInboxPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationsInboxPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_inbox_page_supports_pagination_state(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        Livewire::actingAs($user)
            ->test(NotificationsInboxPage::class)
            ->assertSet('perPage', 25)
            ->set('perPage', 10)
            ->assertSet('page', 1)
            ->call('goToPage', 1)
            ->assertSet('page', 1);
    }
}
