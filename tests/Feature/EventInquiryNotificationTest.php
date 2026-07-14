<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Task;
use App\Models\User;
use App\Services\EventInquiryNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventInquiryNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        foreach (['admin', 'super_admin', 'biuro'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
    }

    public function test_notify_office_about_new_inquiry_creates_tasks_for_admin_and_biuro(): void
    {
        [$admin, $biuro] = $this->makeOfficeUsers();
        $event = $this->makeInquiryEvent($admin);

        app(EventInquiryNotificationService::class)->notifyOfficeAboutNewInquiry($event, $admin);

        $tasks = Task::query()
            ->where('taskable_type', Event::class)
            ->where('taskable_id', $event->id)
            ->get();

        $this->assertCount(2, $tasks);
        $this->assertEqualsCanonicalizing(
            [$admin->id, $biuro->id],
            $tasks->pluck('assignee_id')->all(),
        );

        $editUrl = EventResource::getUrl('edit', ['record' => $event]);
        $this->assertStringContainsString('Nowe zapytanie:', (string) $tasks->first()->title);
        $this->assertStringContainsString($editUrl, (string) $tasks->first()->description);
    }

    public function test_notify_office_skips_duplicate_inquiry_within_five_minutes(): void
    {
        [$admin] = $this->makeOfficeUsers();
        $event = $this->makeInquiryEvent($admin);
        $service = app(EventInquiryNotificationService::class);

        $service->notifyOfficeAboutNewInquiry($event, $admin);
        $service->notifyOfficeAboutNewInquiry($event, $admin);

        $this->assertSame(2, Task::query()->where('taskable_id', $event->id)->count());
    }

    private function makeInquiryEvent(User $creator): Event
    {
        $template = EventTemplate::factory()->create();

        return Event::create([
            'event_template_id' => $template->id,
            'name' => 'Wycieczka szkolna',
            'client_name' => 'Szkoła Testowa',
            'client_phone' => '123456789',
            'client_email' => 'kontakt@szkola.test',
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->addDay()->toDateString(),
            'participant_count' => 30,
            'total_cost' => 15000,
            'status' => Event::STATUS_INQUIRY,
            'created_by' => $creator->id,
        ]);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function makeOfficeUsers(): array
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $biuro = User::factory()->create(['status' => 'active']);
        $biuro->assignRole('biuro');

        return [$admin, $biuro];
    }
}
