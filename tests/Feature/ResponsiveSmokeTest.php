<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Filament\Resources\EventResource\Pages\EditEventProgram;
use App\Filament\Resources\EventResource\Pages\EventFinance;
use App\Filament\Resources\EventResource\Pages\EventHotelPlanning;
use App\Filament\Resources\EventResource\Pages\ManageEventParticipants;
use App\Filament\Resources\TaskResource\Pages\TasksKanbanBoardPage;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke: kluczowe ekrany + markery responsive (Inbox, scroll hint, CSS).
 * Viewporty wizualne: ręczne / przeglądarka — ten test pilnuje 200 + obecność UI.
 */
class ResponsiveSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        $this->event = Event::factory()->create([
            'name' => 'Smoke Responsive Event',
            'status' => Event::STATUS_CONFIRMED,
            'shared_with_pilot' => true,
        ]);

        if (Schema::hasTable('event_settlements')) {
            $payload = ['event_id' => $this->event->id];
            if (Schema::hasColumn('event_settlements', 'status')) {
                $payload['status'] = 'draft';
            }
            if (Schema::hasColumn('event_settlements', 'name')) {
                $payload['name'] = 'Smoke settlement';
            }
            EventSettlement::query()->create($payload);
        }
    }

    public function test_admin_shell_exposes_inbox_and_scroll_hint_css(): void
    {
        $html = $this->get('/admin')->assertSuccessful()->getContent();

        $this->assertStringContainsString('sor-topbar-inbox', $html);
        $this->assertStringContainsString('>Inbox<', $html);
        $this->assertStringContainsString('Przesuń w bok, aby zobaczyć więcej', $html);
        $this->assertStringContainsString('Responsive system — sticky ladder', $html);
    }

    public function test_event_workspace_pages_respond(): void
    {
        $urls = [
            EditEvent::getUrl(['record' => $this->event]),
            EditEventProgram::getUrl(['record' => $this->event]),
            EventHotelPlanning::getUrl(['record' => $this->event]),
            EventFinance::getUrl(['record' => $this->event]),
        ];

        if (Schema::hasTable('event_participants')) {
            $urls[] = ManageEventParticipants::getUrl(['record' => $this->event]);
        }

        foreach ($urls as $url) {
            $this->get($url)->assertSuccessful();
        }
    }

    public function test_chat_and_kanban_respond(): void
    {
        $this->get(\App\Filament\Pages\Chat::getUrl())->assertSuccessful();
        $this->get(TasksKanbanBoardPage::getUrl())->assertSuccessful();
    }

    public function test_event_resource_urls_resolve(): void
    {
        $this->assertNotEmpty(EventResource::getUrl('edit', ['record' => $this->event]));
        $this->assertNotEmpty(EventResource::getUrl('finance', ['record' => $this->event]));
    }
}
