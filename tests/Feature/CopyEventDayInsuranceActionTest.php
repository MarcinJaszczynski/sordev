<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Events\CopyEventDayInsuranceAction;
use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\EventTemplate;
use App\Models\Insurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CopyEventDayInsuranceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_copies_insurance_to_next_day_only(): void
    {
        [$event, $nnw] = $this->makeEventWithInsurances(durationDays: 4);

        $source = EventDayInsurance::create([
            'event_id' => $event->id,
            'day' => 1,
            'insurance_id' => $nnw->id,
        ]);

        $created = app(CopyEventDayInsuranceAction::class)(
            $source,
            CopyEventDayInsuranceAction::MODE_NEXT,
        );

        $this->assertCount(1, $created);
        $this->assertSame(2, (int) $created[0]->day);
        $this->assertSame($nnw->id, (int) $created[0]->insurance_id);
        $this->assertSame(2, EventDayInsurance::query()->where('event_id', $event->id)->count());
    }

    public function test_fills_remaining_days_idempotently(): void
    {
        [$event, $nnw, $kl] = $this->makeEventWithInsurances(durationDays: 4);

        $source = EventDayInsurance::create([
            'event_id' => $event->id,
            'day' => 1,
            'insurance_id' => $nnw->id,
        ]);

        EventDayInsurance::create([
            'event_id' => $event->id,
            'day' => 3,
            'insurance_id' => $nnw->id,
        ]);

        // Inna polisa na dniu 2 — nie powinna blokować kopiowania NNW.
        EventDayInsurance::create([
            'event_id' => $event->id,
            'day' => 2,
            'insurance_id' => $kl->id,
        ]);

        $created = app(CopyEventDayInsuranceAction::class)(
            $source,
            CopyEventDayInsuranceAction::MODE_REMAINING,
        );

        $this->assertCount(2, $created);
        $this->assertSame([2, 4], collect($created)->pluck('day')->sort()->values()->all());

        $nnwDays = EventDayInsurance::query()
            ->where('event_id', $event->id)
            ->where('insurance_id', $nnw->id)
            ->orderBy('day')
            ->pluck('day')
            ->all();

        $this->assertSame([1, 2, 3, 4], $nnwDays);

        // Drugie wywołanie nic nie dodaje.
        $again = app(CopyEventDayInsuranceAction::class)(
            $source,
            CopyEventDayInsuranceAction::MODE_REMAINING,
        );
        $this->assertSame([], $again);
    }

    public function test_does_not_copy_past_core_program_horizon(): void
    {
        [$event, $nnw] = $this->makeEventWithInsurances(durationDays: 2);

        $source = EventDayInsurance::create([
            'event_id' => $event->id,
            'day' => 2,
            'insurance_id' => $nnw->id,
        ]);

        $created = app(CopyEventDayInsuranceAction::class)(
            $source,
            CopyEventDayInsuranceAction::MODE_NEXT,
        );

        $this->assertSame([], $created);
    }

    public function test_relation_manager_copy_actions_sync_settlement(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        [$event, $nnw] = $this->makeEventWithInsurances(durationDays: 3, user: $user);

        $source = EventDayInsurance::create([
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
            ->callTableAction('fill_remaining_days', $source);

        $days = EventDayInsurance::query()
            ->where('event_id', $event->id)
            ->where('insurance_id', $nnw->id)
            ->orderBy('day')
            ->pluck('day')
            ->all();

        $this->assertSame([1, 2, 3], $days);
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
            'name' => 'Impreza kopiowanie ubezpieczeń',
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
