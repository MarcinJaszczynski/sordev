<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Events\AddEventDayInsurancesAction;
use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\EventTemplate;
use App\Models\Insurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AddEventDayInsurancesActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_adds_missing_products_for_a_day_and_skips_existing(): void
    {
        [$event, $nnw, $kl] = $this->makeEventWithInsurances(durationDays: 3);

        EventDayInsurance::create([
            'event_id' => $event->id,
            'day' => 1,
            'insurance_id' => $nnw->id,
            'is_done' => true,
        ]);

        $created = app(AddEventDayInsurancesAction::class)(
            $event,
            1,
            [$nnw->id, $kl->id, $nnw->id, 0],
        );

        $this->assertCount(1, $created);
        $this->assertSame(1, (int) $created[0]->day);
        $this->assertSame($kl->id, (int) $created[0]->insurance_id);

        $this->assertTrue(
            EventDayInsurance::query()
                ->where('event_id', $event->id)
                ->where('day', 1)
                ->where('insurance_id', $nnw->id)
                ->first()
                ?->is_done
        );

        $this->assertSame(2, EventDayInsurance::query()->where('event_id', $event->id)->count());
    }

    public function test_does_not_delete_omitted_products(): void
    {
        [$event, $nnw, $kl] = $this->makeEventWithInsurances(durationDays: 2);

        EventDayInsurance::create([
            'event_id' => $event->id,
            'day' => 1,
            'insurance_id' => $nnw->id,
        ]);

        $created = app(AddEventDayInsurancesAction::class)($event, 1, [$kl->id]);

        $this->assertCount(1, $created);

        $ids = EventDayInsurance::query()
            ->where('event_id', $event->id)
            ->where('day', 1)
            ->orderBy('insurance_id')
            ->pluck('insurance_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertSame([$nnw->id, $kl->id], $ids);
    }

    public function test_does_not_add_outside_event_horizon(): void
    {
        [$event, $nnw] = $this->makeEventWithInsurances(durationDays: 2);

        $this->assertSame([], app(AddEventDayInsurancesAction::class)($event, 0, [$nnw->id]));
        $this->assertSame([], app(AddEventDayInsurancesAction::class)($event, 3, [$nnw->id]));
        $this->assertSame(0, EventDayInsurance::query()->where('event_id', $event->id)->count());
    }

    public function test_relation_manager_adds_products_and_keeps_modal_for_next_day(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        [$event, $nnw, $kl] = $this->makeEventWithInsurances(durationDays: 3, user: $user);

        EventDayInsurance::create([
            'event_id' => $event->id,
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
            ->callTableAction('add_insurances', data: [
                'event_insurance_policy_id' => null,
                'insurance_policy_number' => 'POL-NEXT',
                'insurance_status' => 'in_progress',
                'insurance_payment_status' => 'pending',
                'insurance_amount' => null,
                'insurance_paid_at' => null,
                'insurance_document_path' => null,
                'insurance_insured_list_path' => null,
                'insurance_terms' => null,
                'day' => 1,
                'insurance_ids' => [$kl->id],
            ], arguments: ['next' => true])
            ->assertHasNoTableActionErrors()
            ->assertTableActionHalted('add_insurances');

        $policyId = \App\Models\EventInsurancePolicy::query()->where('event_id', $event->id)->value('id');
        $this->assertNotNull($policyId);

        $dayOne = EventDayInsurance::query()
            ->where('event_id', $event->id)
            ->where('day', 1)
            ->orderBy('insurance_id')
            ->get();

        $this->assertSame([$nnw->id, $kl->id], $dayOne->pluck('insurance_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame((int) $policyId, (int) $dayOne->firstWhere('insurance_id', $kl->id)->event_insurance_policy_id);
        $this->assertSame(0, EventDayInsurance::query()->where('event_id', $event->id)->where('day', 2)->count());
    }

    public function test_relation_manager_save_closes_modal(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        [$event, $nnw] = $this->makeEventWithInsurances(durationDays: 2, user: $user);

        Livewire::actingAs($user)
            ->test(
                \App\Filament\Resources\EventResource\RelationManagers\DayInsurancesRelationManager::class,
                [
                    'ownerRecord' => $event,
                    'pageClass' => \App\Filament\Resources\EventResource\Pages\ManageEventDayInsurances::class,
                ]
            )
            ->callTableAction('add_insurances', data: [
                'event_insurance_policy_id' => null,
                'insurance_policy_number' => 'POL-1',
                'insurance_status' => 'pending',
                'insurance_payment_status' => 'pending',
                'insurance_amount' => null,
                'insurance_paid_at' => null,
                'insurance_document_path' => null,
                'insurance_insured_list_path' => null,
                'insurance_terms' => null,
                'day' => 1,
                'insurance_ids' => [$nnw->id],
            ])
            ->assertHasNoTableActionErrors()
            ->assertTableActionNotMounted('add_insurances');

        $this->assertSame(1, EventDayInsurance::query()->where('event_id', $event->id)->count());
        $this->assertSame(1, \App\Models\EventInsurancePolicy::query()->where('event_id', $event->id)->count());
    }

    public function test_save_and_next_reuses_same_policy(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        [$event, $nnw] = $this->makeEventWithInsurances(durationDays: 3, user: $user);

        $component = Livewire::actingAs($user)
            ->test(
                \App\Filament\Resources\EventResource\RelationManagers\DayInsurancesRelationManager::class,
                [
                    'ownerRecord' => $event,
                    'pageClass' => \App\Filament\Resources\EventResource\Pages\ManageEventDayInsurances::class,
                ]
            )
            ->callTableAction('add_insurances', data: [
                'event_insurance_policy_id' => null,
                'insurance_policy_number' => 'SHARED',
                'insurance_status' => 'in_progress',
                'insurance_payment_status' => 'pending',
                'insurance_amount' => 50,
                'insurance_paid_at' => null,
                'insurance_document_path' => null,
                'insurance_insured_list_path' => null,
                'insurance_terms' => null,
                'day' => 1,
                'insurance_ids' => [$nnw->id],
            ], arguments: ['next' => true])
            ->assertTableActionHalted('add_insurances');

        $policyId = (int) \App\Models\EventInsurancePolicy::query()->where('event_id', $event->id)->value('id');

        $component
            ->callTableAction('add_insurances', data: [
                'event_insurance_policy_id' => $policyId,
                'insurance_policy_number' => 'SHARED',
                'insurance_status' => 'in_progress',
                'insurance_payment_status' => 'pending',
                'insurance_amount' => 50,
                'insurance_paid_at' => null,
                'insurance_document_path' => null,
                'insurance_insured_list_path' => null,
                'insurance_terms' => null,
                'day' => 2,
                'insurance_ids' => [$nnw->id],
            ])
            ->assertHasNoTableActionErrors()
            ->assertTableActionNotMounted('add_insurances');

        $this->assertSame(1, \App\Models\EventInsurancePolicy::query()->where('event_id', $event->id)->count());
        $this->assertSame(
            [$policyId, $policyId],
            EventDayInsurance::query()
                ->where('event_id', $event->id)
                ->orderBy('day')
                ->pluck('event_insurance_policy_id')
                ->map(fn ($id) => (int) $id)
                ->all()
        );
    }

    /**
     * @return array{0: Event, 1: Insurance, 2: Insurance}
     */
    private function makeEventWithInsurances(int $durationDays, ?User $user = null): array
    {
        $user ??= User::factory()->create();
        $template = EventTemplate::factory()->create([
            'duration_days' => $durationDays,
        ]);

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza dodawanie ubezpieczeń',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays($durationDays - 1)->toDateString(),
            'duration_days' => $durationDays,
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $nnw = Insurance::create([
            'name' => 'NNW',
            'price_per_person' => 5,
            'active' => true,
            'insurance_enabled' => true,
            'coverage_type' => 'nnw',
        ]);

        $kl = Insurance::create([
            'name' => 'KL',
            'price_per_person' => 8,
            'active' => true,
            'insurance_enabled' => true,
            'coverage_type' => 'kl',
        ]);

        return [$event, $nnw, $kl];
    }
}
