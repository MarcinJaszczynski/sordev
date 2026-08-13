<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Filament\Resources\EventResource\Pages\EventFinance;
use App\Filament\Resources\EventResource\Pages\ManageEventParticipants;
use App\Filament\Resources\TaskResource\Pages\TasksKanbanBoardPage;
use App\Livewire\ChatInterface;
use App\Livewire\EventHotelPlanEditor;
use App\Livewire\EventParticipantListEditor;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ResponsiveVisibilityAuditTest extends TestCase
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
            'name' => 'Audit Responsive Event',
            'status' => Event::STATUS_CONFIRMED,
        ]);
    }

    public function test_admin_shell_includes_responsive_system_css(): void
    {
        $html = $this->get('/admin')->assertSuccessful()->getContent();

        $this->assertStringContainsString('Responsive system — sticky ladder', $html);
        $this->assertStringContainsString('--sor-chrome-top', $html);
        $this->assertStringContainsString('sor-chat-shell', $html);
        $this->assertStringContainsString('Na telefonie/tablecie wyłączamy sticky', $html);
    }

    public function test_kanban_page_uses_responsive_filter_grid(): void
    {
        Livewire::test(TasksKanbanBoardPage::class)
            ->assertSeeHtml('grid-cols-1')
            ->assertSeeHtml('sm:grid-cols-2');
    }

    public function test_chat_exposes_mobile_pane_shell(): void
    {
        Livewire::test(ChatInterface::class)
            ->assertSeeHtml('sor-chat-shell')
            ->assertSeeHtml('data-mobile-pane')
            ->assertSeeHtml('sor-chat-aside')
            ->assertSeeHtml('sor-chat-main');
    }

    public function test_participant_and_hotel_editors_use_sor_sticky_toolbar(): void
    {
        Livewire::test(EventParticipantListEditor::class, ['eventId' => $this->event->id])
            ->assertSeeHtml('sor-sticky-toolbar')
            ->assertDontSeeHtml('sticky top-0');

        Livewire::test(EventHotelPlanEditor::class, ['eventId' => $this->event->id])
            ->assertSeeHtml('sor-sticky-toolbar')
            ->assertDontSeeHtml('sticky top-0');
    }

    public function test_event_finance_drawer_stats_are_responsive(): void
    {
        $html = file_get_contents(resource_path('views/filament/resources/event-resource/pages/event-finance.blade.php'));

        $this->assertIsString($html);
        $this->assertStringContainsString('grid-cols-1 gap-2 text-center text-xs sm:grid-cols-3', $html);
        $this->assertStringNotContainsString('mb-3 grid grid-cols-3 gap-2 text-center text-xs', $html);
    }

    public function test_shared_form_columns_are_breakpoint_aware(): void
    {
        $keyInfo = file_get_contents(app_path('Filament/Forms/EventKeyInfoFields.php'));
        $readiness = file_get_contents(app_path('Filament/Forms/EventReadinessFields.php'));

        $this->assertIsString($keyInfo);
        $this->assertIsString($readiness);
        $this->assertStringContainsString("['default' => 1, 'md' => 2", $keyInfo);
        $this->assertStringContainsString("['default' => 1, 'md' => 2", $readiness);
        $this->assertDoesNotMatchRegularExpression('/->columns\([234]\)/', $keyInfo);
        $this->assertDoesNotMatchRegularExpression('/->columns\([234]\)/', $readiness);
    }

    public function test_event_edit_and_participants_pages_render(): void
    {
        $this->get(EditEvent::getUrl(['record' => $this->event]))->assertSuccessful();
        $this->get(ManageEventParticipants::getUrl(['record' => $this->event]))->assertSuccessful();
    }

    public function test_event_finance_page_renders_with_settlement(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('event_settlements')) {
            $this->markTestSkipped('Brak tabeli event_settlements.');
        }

        \App\Models\EventSettlement::query()->create(array_filter([
            'event_id' => $this->event->id,
            'status' => \Illuminate\Support\Facades\Schema::hasColumn('event_settlements', 'status') ? 'draft' : null,
            'name' => \Illuminate\Support\Facades\Schema::hasColumn('event_settlements', 'name') ? 'Audit settlement' : null,
        ]));

        $this->get(EventFinance::getUrl(['record' => $this->event]))->assertSuccessful();
    }
}
