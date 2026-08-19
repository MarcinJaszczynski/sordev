<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Models\Contact;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventCodeFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_create_event_without_code_assigns_generated_code(): void
    {
        $this->actingAsAdmin();
        [$place, $template] = $this->makeTemplateContext();

        Livewire::test(CreateEvent::class)
            ->call('applyClientLookup', $this->orderingPartyPayload(), 'Jan Kowalski · Szkoła', 'jan@example.com', '500600700')
            ->fillForm([
                'event_template_id' => $template->id,
                'name' => 'Wycieczka bez kodu',
                'start_date' => '2026-08-01',
                'duration_days' => 2,
                'participant_count' => 20,
                'gratis_count' => 2,
                'start_place_id' => $place->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->where('name', 'Wycieczka bez kodu')->first();

        $this->assertNotNull($event);
        $this->assertNotNull($event->code);
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{8}$/', $event->code);
    }

    public function test_create_event_saves_manual_code(): void
    {
        $this->actingAsAdmin();
        [$place, $template] = $this->makeTemplateContext();

        Livewire::test(CreateEvent::class)
            ->call('applyClientLookup', $this->orderingPartyPayload(), 'Jan Kowalski · Szkoła', 'jan@example.com', '500600700')
            ->fillForm([
                'event_template_id' => $template->id,
                'name' => 'Wycieczka gminna',
                'code' => 'zp.271.12.2026',
                'start_date' => '2026-08-01',
                'duration_days' => 2,
                'participant_count' => 20,
                'gratis_count' => 2,
                'start_place_id' => $place->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('events', [
            'name' => 'Wycieczka gminna',
            'code' => 'ZP.271.12.2026',
        ]);
    }

    public function test_create_event_rejects_duplicate_code(): void
    {
        $this->actingAsAdmin();
        Event::factory()->create(['code' => 'ZP.271.12.2026']);
        [$place, $template] = $this->makeTemplateContext();

        Livewire::test(CreateEvent::class)
            ->call('applyClientLookup', $this->orderingPartyPayload(), 'Jan Kowalski · Szkoła', 'jan@example.com', '500600700')
            ->fillForm([
                'event_template_id' => $template->id,
                'name' => 'Duplikat kodu',
                'code' => 'ZP.271.12.2026',
                'start_date' => '2026-08-01',
                'duration_days' => 2,
                'participant_count' => 20,
                'gratis_count' => 2,
                'start_place_id' => $place->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['code']);
    }

    public function test_edit_event_can_change_code(): void
    {
        $this->actingAsAdmin();
        $contractor = Contractor::create(['name' => 'Klient testowy', 'status' => 'active']);
        $event = Event::factory()->create([
            'code' => '26ABCDEF',
            'client_name' => 'Klient testowy',
            'contractor_id' => $contractor->id,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        Livewire::test(EditEvent::class, ['record' => $event->getKey()])
            ->fillForm([
                'code' => 'um/gmina-1',
                'ordering_parties' => [
                    [
                        'contact_id' => null,
                        'contractor_id' => (string) $contractor->id,
                        'department_label' => null,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('UM/GMINA-1', $event->fresh()->code);
    }

    public function test_edit_event_rejects_duplicate_code(): void
    {
        $this->actingAsAdmin();
        Event::factory()->create(['code' => 'TAKEN001']);
        $contractor = Contractor::create(['name' => 'Klient testowy', 'status' => 'active']);
        $event = Event::factory()->create([
            'code' => '26ABCDEF',
            'client_name' => 'Klient testowy',
            'contractor_id' => $contractor->id,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        Livewire::test(EditEvent::class, ['record' => $event->getKey()])
            ->fillForm([
                'code' => 'TAKEN001',
                'ordering_parties' => [
                    [
                        'contact_id' => null,
                        'contractor_id' => (string) $contractor->id,
                        'department_label' => null,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['code']);

        $this->assertSame('26ABCDEF', $event->fresh()->code);
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        return $admin;
    }

    /**
     * @return array{0: Place, 1: EventTemplate}
     */
    private function makeTemplateContext(): array
    {
        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'start_place_id' => $place->id,
            'duration_days' => 2,
        ]);

        return [$place, $template];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orderingPartyPayload(): array
    {
        $contractor = Contractor::create([
            'name' => 'Szkoła',
            'status' => 'active',
        ]);
        $contact = Contact::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.com',
            'phone' => '500600700',
        ]);
        $contractor->contacts()->attach($contact->id);

        return [[
            'contact_id' => (string) $contact->id,
            'contractor_id' => (string) $contractor->id,
            'department_label' => null,
        ]];
    }
}
