<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Events\SyncEventDayInsurancesFromTemplateAction;
use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\EventTemplate;
use App\Models\EventTemplateDayInsurance;
use App\Models\Insurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SyncEventDayInsurancesFromTemplateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_template_insurances_within_event_horizon(): void
    {
        [$event, $template, $nnw, $kl] = $this->makeEventAndTemplate(eventDays: 4, templateDays: 5);

        EventTemplateDayInsurance::create([
            'event_template_id' => $template->id,
            'day' => 1,
            'insurance_id' => $nnw->id,
        ]);
        EventTemplateDayInsurance::create([
            'event_template_id' => $template->id,
            'day' => 1,
            'insurance_id' => $kl->id,
        ]);
        EventTemplateDayInsurance::create([
            'event_template_id' => $template->id,
            'day' => 5,
            'insurance_id' => $kl->id,
        ]);

        $created = app(SyncEventDayInsurancesFromTemplateAction::class)($event, $template);

        $this->assertCount(2, $created);

        $rows = EventDayInsurance::query()
            ->where('event_id', $event->id)
            ->orderBy('day')
            ->orderBy('insurance_id')
            ->get(['day', 'insurance_id']);

        $this->assertSame(
            collect([
                ['day' => 1, 'insurance_id' => $nnw->id],
                ['day' => 1, 'insurance_id' => $kl->id],
            ])->sortBy([
                ['day', 'asc'],
                ['insurance_id', 'asc'],
            ])->values()->all(),
            $rows->map(fn (EventDayInsurance $row): array => [
                'day' => (int) $row->day,
                'insurance_id' => (int) $row->insurance_id,
            ])->all(),
        );
    }

    public function test_is_idempotent_and_does_not_overwrite_existing(): void
    {
        [$event, $template, $nnw] = $this->makeEventAndTemplate(eventDays: 3, templateDays: 3);

        EventTemplateDayInsurance::create([
            'event_template_id' => $template->id,
            'day' => 1,
            'insurance_id' => $nnw->id,
        ]);
        EventTemplateDayInsurance::create([
            'event_template_id' => $template->id,
            'day' => 2,
            'insurance_id' => $nnw->id,
        ]);

        EventDayInsurance::create([
            'event_id' => $event->id,
            'day' => 1,
            'insurance_id' => $nnw->id,
            'is_done' => true,
        ]);

        $created = app(SyncEventDayInsurancesFromTemplateAction::class)($event, $template);

        $this->assertCount(1, $created);
        $this->assertSame(2, (int) $created[0]->day);

        $this->assertTrue(
            EventDayInsurance::query()
                ->where('event_id', $event->id)
                ->where('day', 1)
                ->where('insurance_id', $nnw->id)
                ->first()
                ?->is_done
        );

        $again = app(SyncEventDayInsurancesFromTemplateAction::class)($event, $template);
        $this->assertSame([], $again);
        $this->assertSame(2, EventDayInsurance::query()->where('event_id', $event->id)->count());
    }

    public function test_create_from_template_copies_day_insurances(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $template = EventTemplate::factory()->create(['duration_days' => 2]);
        $nnw = $this->makeInsurance('NNW', 'nnw');

        EventTemplateDayInsurance::create([
            'event_template_id' => $template->id,
            'day' => 1,
            'insurance_id' => $nnw->id,
        ]);
        EventTemplateDayInsurance::create([
            'event_template_id' => $template->id,
            'day' => 2,
            'insurance_id' => $nnw->id,
        ]);

        $event = Event::createFromTemplate($template, [
            'name' => 'Impreza z ubezpieczeniami',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'participant_count' => 10,
            'created_by' => $user->id,
        ]);

        $this->assertSame(2, EventDayInsurance::query()->where('event_id', $event->id)->count());
    }

    public function test_relation_manager_imports_from_selected_template_when_event_has_none(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        [$event, $template, $nnw] = $this->makeEventAndTemplate(
            eventDays: 2,
            templateDays: 2,
            user: $user,
            attachTemplate: false,
        );

        EventTemplateDayInsurance::create([
            'event_template_id' => $template->id,
            'day' => 1,
            'insurance_id' => $nnw->id,
        ]);

        Livewire::actingAs($user)
            ->test(
                \App\Filament\Resources\EventResource\RelationManagers\DayInsurancesRelationManager::class,
                [
                    'ownerRecord' => $event,
                    'pageClass' => \App\Filament\Resources\EventResource\Pages\ManageEventDayInsurances::class,
                ]
            )
            ->callTableAction('import_from_template', data: [
                'event_template_id' => $template->id,
            ]);

        $this->assertSame(1, EventDayInsurance::query()->where('event_id', $event->id)->count());
        $this->assertNull($event->fresh()->event_template_id);
    }

    /**
     * @return array{0: Event, 1: EventTemplate, 2: Insurance, 3?: Insurance}
     */
    private function makeEventAndTemplate(
        int $eventDays,
        int $templateDays,
        ?User $user = null,
        bool $attachTemplate = true,
    ): array {
        $user ??= User::factory()->create();
        $template = EventTemplate::factory()->create([
            'duration_days' => $templateDays,
        ]);

        $event = Event::create([
            'event_template_id' => $attachTemplate ? $template->id : null,
            'name' => 'Impreza import ubezpieczeń',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays($eventDays - 1)->toDateString(),
            'duration_days' => $eventDays,
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $nnw = $this->makeInsurance('NNW', 'nnw');
        $kl = $this->makeInsurance('KL', 'kl');

        return [$event, $template, $nnw, $kl];
    }

    private function makeInsurance(string $name, string $coverageType): Insurance
    {
        return Insurance::create([
            'name' => $name,
            'price_per_person' => 5,
            'active' => true,
            'insurance_enabled' => true,
            'coverage_type' => $coverageType,
        ]);
    }
}
