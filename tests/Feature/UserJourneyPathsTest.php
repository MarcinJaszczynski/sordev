<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Events\ChangeEventStatusAction;
use App\Data\ChangeEventStatusData;
use App\Filament\Pages\EventsSalesPipelinePage;
use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Resources\EventTemplateResource\Pages\CreateEventTemplate;
use App\Filament\Resources\EventTemplateResource\Pages\EditEventTemplate;
use App\Models\Event;
use App\Models\EventHistory;
use App\Models\EventSnapshot;
use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use App\Models\Place;
use App\Models\Task;
use App\Models\User;
use App\Services\ClientLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ścieżki użytkownika: szablon → impreza → ścieżka oferty → potwierdzenie.
 *
 * Cel: zweryfikować efekt końcowy typowych schematów pracy biura.
 */
class UserJourneyPathsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        foreach (['admin', 'super_admin', 'biuro'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    public function test_create_event_template_happy_path(): void
    {
        Livewire::test(CreateEventTemplate::class)
            ->fillForm([
                'name' => 'Szlak Orlich Gniazd',
                'slug' => 'szlak-orlich-gniazd',
                'duration_days' => 3,
                'is_active' => true,
                'subtitle' => 'Oferta szkolna',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('event_templates', [
            'name' => 'Szlak Orlich Gniazd',
            'slug' => 'szlak-orlich-gniazd',
            'duration_days' => 3,
            'is_active' => 1,
        ]);
    }

    public function test_clone_event_template_copies_program_points_and_redirects(): void
    {
        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'name' => 'Szablon źródłowy',
            'slug' => 'szablon-zrodlowy',
            'start_place_id' => $place->id,
            'duration_days' => 2,
            'is_active' => true,
        ]);

        $point = EventTemplateProgramPoint::factory()->create(['name' => 'Zwiedzanie zamku']);
        $template->programPoints()->attach($point->id, [
            'day' => 1,
            'order' => 1,
            'notes' => 'Start 10:00',
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        Livewire::test(EditEventTemplate::class, ['record' => $template->getKey()])
            ->callAction('clone');

        $clone = EventTemplate::query()
            ->where('name', 'Szablon źródłowy (Kopia)')
            ->first();

        $this->assertNotNull($clone);
        $this->assertNotSame($template->id, $clone->id);
        $this->assertStringContainsString('szablon-zrodlowy-kopia-', (string) $clone->slug);
        $this->assertSame(2, (int) $clone->duration_days);
        $this->assertTrue((bool) $clone->is_active);
        $this->assertCount(1, $clone->programPoints);
        $this->assertSame('Zwiedzanie zamku', $clone->programPoints->first()->name);
        $this->assertSame(1, (int) $clone->programPoints->first()->pivot->day);
    }

    public function test_create_event_without_template_starts_as_inquiry(): void
    {
        $place = Place::factory()->starting()->create();

        $payload = app(ClientLookupService::class)->quickCreate([
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'phone' => '501502503',
            'email' => 'anna@example.com',
        ]);

        Livewire::test(CreateEvent::class)
            ->call(
                'applyClientLookup',
                $payload['ordering_parties'],
                $payload['client_name'],
                $payload['client_email'],
                $payload['client_phone'],
            )
            ->fillForm([
                'event_template_id' => null,
                'name' => 'Impreza bez szablonu',
                'start_date' => '2026-09-10',
                'duration_days' => 1,
                'participant_count' => 12,
                'start_place_id' => $place->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->where('name', 'Impreza bez szablonu')->first();

        $this->assertNotNull($event);
        $this->assertNull($event->event_template_id);
        $this->assertSame(Event::STATUS_INQUIRY, $event->status);
        $this->assertSame('501502503', $event->client_phone);
    }

    public function test_create_event_from_template_via_ui_copies_template_and_status_inquiry(): void
    {
        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'name' => 'Szablon UI',
            'start_place_id' => $place->id,
            'duration_days' => 2,
            'transfer_km' => 120,
            'program_km' => 80,
        ]);

        $payload = app(ClientLookupService::class)->quickCreate([
            'first_name' => 'Piotr',
            'last_name' => 'Kowalski',
            'phone' => '600700800',
        ]);

        Livewire::test(CreateEvent::class)
            ->call(
                'applyClientLookup',
                $payload['ordering_parties'],
                $payload['client_name'],
                $payload['client_email'],
                $payload['client_phone'],
            )
            ->fillForm([
                'event_template_id' => $template->id,
                'name' => 'Impreza ze szablonu UI',
                'start_date' => '2026-10-01',
                'duration_days' => 2,
                'participant_count' => 25,
                'start_place_id' => $place->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->where('name', 'Impreza ze szablonu UI')->first();

        $this->assertNotNull($event);
        $this->assertSame($template->id, $event->event_template_id);
        $this->assertSame(Event::STATUS_INQUIRY, $event->status);
        $this->assertSame(120.0, (float) $event->transfer_km);
        $this->assertSame(80.0, (float) $event->program_km);
        $this->assertDatabaseHas('event_snapshots', [
            'event_id' => $event->id,
            'type' => 'original',
        ]);
    }

    public function test_sales_path_moves_inquiry_to_offer_to_provisional_to_confirmed(): void
    {
        $event = Event::factory()->create([
            'status' => Event::STATUS_INQUIRY,
            'created_by' => $this->admin->id,
            'name' => 'Ścieżka oferty — test',
        ]);

        $component = Livewire::test(EventsSalesPipelinePage::class);

        $component->call('moveEvent', $event->id, Event::STATUS_OFFER);
        $this->assertSame(Event::STATUS_OFFER, $event->fresh()->status);

        $component->call('moveEvent', $event->id, Event::STATUS_PROVISIONAL_RESERVATION);
        $this->assertSame(Event::STATUS_PROVISIONAL_RESERVATION, $event->fresh()->status);

        $component->call('moveEvent', $event->id, Event::STATUS_CONFIRMED);
        $event = $event->fresh();
        $this->assertSame(Event::STATUS_CONFIRMED, $event->status);

        // Po potwierdzeniu impreza znika ze ścieżki oferty.
        $columns = $component->instance()->columns();
        foreach ($columns as $statusEvents) {
            $this->assertFalse(
                $statusEvents->contains(fn (Event $row): bool => $row->id === $event->id),
                'Potwierdzona impreza nie powinna być na ścieżce oferty.',
            );
        }

        $this->assertTrue(
            EventHistory::query()
                ->where('event_id', $event->id)
                ->where('action', 'status_changed')
                ->get()
                ->contains(function ($history): bool {
                    $value = $history->new_value;

                    return $value === Event::STATUS_CONFIRMED
                        || $value === [Event::STATUS_CONFIRMED]
                        || (is_string($value) && trim($value, '"') === Event::STATUS_CONFIRMED);
                }),
            'Historia powinna zawierać zmianę statusu na potwierdzoną.',
        );

        $this->assertTrue(
            EventSnapshot::query()
                ->where('event_id', $event->id)
                ->where('type', 'status_change')
                ->exists(),
            'Potwierdzenie powinno utworzyć snapshot statusu.',
        );
    }

    public function test_sales_path_rejects_disallowed_status(): void
    {
        $event = Event::factory()->create([
            'status' => Event::STATUS_INQUIRY,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(EventsSalesPipelinePage::class)
            ->call('moveEvent', $event->id, Event::STATUS_SETTLED);

        $this->assertSame(Event::STATUS_INQUIRY, $event->fresh()->status);
    }

    public function test_change_event_status_action_rejects_unknown_status(): void
    {
        $event = Event::factory()->create([
            'status' => Event::STATUS_INQUIRY,
            'created_by' => $this->admin->id,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(ChangeEventStatusAction::class)(new ChangeEventStatusData(
            event: $event,
            status: 'not_a_real_status',
            reason: 'test',
        ));
    }

    public function test_status_change_to_offer_does_not_create_office_task_nor_send_mail(): void
    {
        $biuro = User::factory()->create(['status' => 'active']);
        $biuro->assignRole('biuro');

        $event = Event::factory()->create([
            'status' => Event::STATUS_INQUIRY,
            'created_by' => $this->admin->id,
            'name' => 'Oferta automatyzacja',
            'client_phone' => '511522533',
            'client_email' => 'klient@example.com',
        ]);

        Mail::fake();

        app(ChangeEventStatusAction::class)(new ChangeEventStatusData(
            event: $event,
            status: Event::STATUS_OFFER,
            reason: 'Test ścieżki',
        ));

        $this->assertSame(Event::STATUS_OFFER, $event->fresh()->status);
        Mail::assertNothingSent();

        $this->assertFalse(
            Task::query()
                ->where('taskable_type', Event::class)
                ->where('taskable_id', $event->id)
                ->where('description', 'like', '%event-status:'.$event->id.':offer%')
                ->exists(),
        );
    }

    public function test_status_change_to_offer_does_not_auto_send_mail_even_with_mailto_email(): void
    {
        Mail::fake();

        $event = Event::factory()->create([
            'status' => Event::STATUS_INQUIRY,
            'created_by' => $this->admin->id,
            'name' => 'Oferta z mailto',
            'client_email' => 'mailto:agula3000@wp.pl',
        ]);

        app(ChangeEventStatusAction::class)(new ChangeEventStatusData(
            event: $event,
            status: Event::STATUS_OFFER,
            reason: 'Test mailto',
        ));

        $this->assertSame(Event::STATUS_OFFER, $event->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_status_change_to_confirmed_creates_office_task(): void
    {
        $biuro = User::factory()->create(['status' => 'active']);
        $biuro->assignRole('biuro');

        $event = Event::factory()->create([
            'status' => Event::STATUS_OFFER,
            'created_by' => $this->admin->id,
            'assigned_to' => $biuro->id,
            'name' => 'Potwierdzenie automatyzacja',
        ]);

        Mail::fake();

        app(ChangeEventStatusAction::class)(new ChangeEventStatusData(
            event: $event,
            status: Event::STATUS_CONFIRMED,
            reason: 'Test potwierdzenia',
        ));

        $this->assertSame(Event::STATUS_CONFIRMED, $event->fresh()->status);
        Mail::assertNothingSent();

        $tasks = Task::query()
            ->where('taskable_type', Event::class)
            ->where('taskable_id', $event->id)
            ->get();

        $this->assertTrue(
            $tasks->contains(fn (Task $task): bool => str_contains((string) $task->title, 'Impreza potwierdzona')),
        );
        $this->assertTrue(
            $tasks->contains(fn (Task $task): bool => str_contains((string) $task->description, 'event-status:'.$event->id.':confirmed')),
        );
    }

    public function test_full_journey_template_to_confirmed_event(): void
    {
        // 1) Szablon
        Livewire::test(CreateEventTemplate::class)
            ->fillForm([
                'name' => 'Journey Template',
                'slug' => 'journey-template',
                'duration_days' => 2,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = EventTemplate::query()->where('slug', 'journey-template')->firstOrFail();
        $place = Place::factory()->starting()->create();
        $template->update(['start_place_id' => $place->id]);

        // 2) Impreza ze szablonu
        $payload = app(ClientLookupService::class)->quickCreate([
            'first_name' => 'Ewa',
            'last_name' => 'Lis',
            'email' => 'ewa@example.com',
        ]);

        Livewire::test(CreateEvent::class)
            ->call(
                'applyClientLookup',
                $payload['ordering_parties'],
                $payload['client_name'],
                $payload['client_email'],
                $payload['client_phone'],
            )
            ->fillForm([
                'event_template_id' => $template->id,
                'name' => 'Journey Event',
                'start_date' => '2026-11-01',
                'duration_days' => 2,
                'participant_count' => 18,
                'start_place_id' => $place->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->where('name', 'Journey Event')->firstOrFail();
        $this->assertSame(Event::STATUS_INQUIRY, $event->status);
        $this->assertSame($template->id, $event->event_template_id);

        // 3) Ścieżka oferty → potwierdzenie
        $board = Livewire::test(EventsSalesPipelinePage::class);
        $board->call('moveEvent', $event->id, Event::STATUS_OFFER);
        $board->call('moveEvent', $event->id, Event::STATUS_CONFIRMED);

        $event = $event->fresh();
        $this->assertSame(Event::STATUS_CONFIRMED, $event->status);
        $this->assertTrue(
            Task::query()
                ->where('taskable_id', $event->id)
                ->where('title', 'like', 'Impreza potwierdzona%')
                ->exists(),
        );
    }

    public function test_confirmed_to_settle_to_settled_creates_tasks_and_snapshots(): void
    {
        User::factory()->create(['status' => 'active'])->assignRole('biuro');

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'created_by' => $this->admin->id,
            'name' => 'Do rozliczenia',
        ]);

        $action = app(ChangeEventStatusAction::class);

        $action(new ChangeEventStatusData(
            event: $event,
            status: Event::STATUS_TO_SETTLE,
            reason: 'Powrót z wyjazdu',
        ));

        $event = $event->fresh();
        $this->assertSame(Event::STATUS_TO_SETTLE, $event->status);
        $this->assertFalse(
            Task::query()
                ->where('taskable_id', $event->id)
                ->where('description', 'like', '%event-status:'.$event->id.':to_settle%')
                ->exists(),
        );
        $this->assertTrue(
            EventSnapshot::query()
                ->where('event_id', $event->id)
                ->where('type', 'status_change')
                ->exists(),
        );

        $action(new ChangeEventStatusData(
            event: $event,
            status: Event::STATUS_SETTLED,
            reason: 'Rozliczenie domknięte',
        ));

        $this->assertSame(Event::STATUS_SETTLED, $event->fresh()->status);
        $this->assertTrue(
            EventHistory::query()
                ->where('event_id', $event->id)
                ->where('action', 'status_changed')
                ->get()
                ->contains(fn ($h): bool => $h->new_value === Event::STATUS_SETTLED
                    || (is_string($h->new_value) && trim($h->new_value, '"') === Event::STATUS_SETTLED)),
        );
    }

    public function test_pending_cancellation_to_cancelled_writes_history_and_snapshot(): void
    {
        $event = Event::factory()->create([
            'status' => Event::STATUS_PENDING_CANCELLATION,
            'created_by' => $this->admin->id,
        ]);

        app(ChangeEventStatusAction::class)(new ChangeEventStatusData(
            event: $event,
            status: Event::STATUS_CANCELLED,
            reason: 'Klient zrezygnował',
        ));

        $event = $event->fresh();
        $this->assertSame(Event::STATUS_CANCELLED, $event->status);
        $this->assertTrue(
            EventSnapshot::query()
                ->where('event_id', $event->id)
                ->where('type', 'status_change')
                ->exists(),
        );
        $this->assertTrue(
            EventHistory::query()
                ->where('event_id', $event->id)
                ->where('action', 'status_changed')
                ->get()
                ->contains(fn ($h): bool => $h->new_value === Event::STATUS_CANCELLED
                    || (is_string($h->new_value) && trim($h->new_value, '"') === Event::STATUS_CANCELLED)),
        );
    }
}
