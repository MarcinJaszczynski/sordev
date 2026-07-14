<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PilotDemoSeeder extends Seeder
{
    public const DEFAULT_EMAIL = 'pilot@test.local';

    public const DEFAULT_PASSWORD = 'pilot123';

    public const DEFAULT_NAME = 'Pilot Testowy';

    /**
     * @return array{pilot: User, events: \Illuminate\Support\Collection<int, Event>, credentials: array{email: string, password: string}}
     */
    public function runWithOptions(string $email = self::DEFAULT_EMAIL, string $password = self::DEFAULT_PASSWORD, string $name = self::DEFAULT_NAME): array
    {
        $this->ensurePilotRoleAndPermissions();

        $pilot = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'type' => 'pilot',
                'status' => 'active',
            ],
        );

        if (! $pilot->hasRole('pilot')) {
            $pilot->assignRole('pilot');
        }

        $legacyPilot = User::where('email', 'piotr.zielinski@example.com')->first();

        if ($legacyPilot && ! $legacyPilot->hasRole('pilot')) {
            $legacyPilot->assignRole('pilot');
            $legacyPilot->update(['status' => 'active', 'type' => 'pilot']);
        }

        $events = $this->assignDemoEvents($pilot);
        $this->ensureSettlements($pilot, $events);

        return [
            'pilot' => $pilot->fresh(),
            'events' => $events,
            'credentials' => [
                'email' => $email,
                'password' => $password,
            ],
        ];
    }

    public function run(): void
    {
        $this->runWithOptions();
    }

    protected function ensurePilotRoleAndPermissions(): void
    {
        Role::firstOrCreate(['name' => 'pilot', 'guard_name' => 'web']);

        foreach (['view_own_event', 'update_own_settlement'] as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        $pilotRole = Role::where('name', 'pilot')->first();

        if ($pilotRole) {
            $pilotRole->syncPermissions([
                'view task',
                'view event_template',
                'view markup',
                'view_own_event',
                'update_own_settlement',
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Event>
     */
    protected function assignDemoEvents(User $pilot): \Illuminate\Support\Collection
    {
        $assigned = Event::query()
            ->forPilot($pilot)
            ->orderByDesc('start_date')
            ->get();

        $targetCount = 3;

        if ($assigned->count() >= $targetCount) {
            return $assigned->take($targetCount);
        }

        $needed = $targetCount - $assigned->count();

        $candidates = Event::query()
            ->where('status', '!=', Event::STATUS_CANCELLED)
            ->where(function ($query) use ($pilot) {
                $query->whereNull('assigned_to')
                    ->orWhere('assigned_to', '!=', $pilot->id);
            })
            ->whereIn('status', [
                Event::STATUS_CONFIRMED,
                Event::STATUS_TO_SETTLE,
                Event::STATUS_SETTLED,
                Event::STATUS_PROVISIONAL_RESERVATION,
            ])
            ->orderByDesc('start_date')
            ->limit($needed)
            ->get();

        foreach ($candidates as $event) {
            $payload = ['assigned_to' => $pilot->id];

            if (Schema::hasColumn('events', 'shared_with_pilot')) {
                $payload['shared_with_pilot'] = true;
                $payload['shared_with_pilot_at'] = now();
            }

            $event->update($payload);

            if (Schema::hasColumn('events', 'pilot_notes') && blank($event->pilot_notes)) {
                $event->update([
                    'pilot_notes' => '<p>Uwagi demo dla pilota — impreza #'.$event->id.'.</p>',
                ]);
            }
        }

        $assigned = Event::query()
            ->forPilot($pilot)
            ->orderByDesc('start_date')
            ->get();

        if ($assigned->isEmpty()) {
            $demo = $this->createDemoEvent($pilot);
            $assigned = collect([$demo]);
        }

        return $assigned->take($targetCount);
    }

    protected function createDemoEvent(User $pilot): Event
    {
        return Event::create([
            'name' => 'Demo — wycieczka pilota',
            'client_name' => 'Klient testowy SOR',
            'client_email' => 'klient@test.local',
            'client_phone' => '+48 500 000 000',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(13)->toDateString(),
            'duration_days' => 4,
            'participant_count' => 32,
            'transfer_km' => 150,
            'program_km' => 420,
            'status' => Event::STATUS_CONFIRMED,
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'shared_with_pilot_at' => now(),
            'pilot_notes' => '<p><strong>Demo teczki pilota.</strong> Sprawdź godziny zbiórek i kontakt z kierowcą.</p>',
            'transport_company_name' => 'Demo Transport Sp. z o.o.',
            'driver_name' => 'Jan Kierowca',
            'driver_phone' => '+48 600 111 222',
            'vehicle_registration' => 'WA 12345',
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Event>  $events
     */
    protected function ensureSettlements(User $pilot, \Illuminate\Support\Collection $events): void
    {
        if (! Schema::hasTable('event_settlements')) {
            return;
        }

        foreach ($events as $event) {
            $settlement = EventSettlement::findOrCreateActiveForEvent($event);

            if (! $settlement->pilot_id) {
                $settlement->update(['pilot_id' => $pilot->id]);
            }

            if ($settlement->status === 'draft') {
                $settlement->update(['status' => 'active']);
            }
        }
    }
}
