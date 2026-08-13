<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\ImportExportPanel;
use App\Filament\Resources\EventResource;
use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Event;
use App\Models\EventAgreement;
use App\Models\OnlinePaymentSession;
use App\Models\User;
use App\Support\SecurityEnvironment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function withProductionEnv(callable $callback): mixed
    {
        $previous = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            return $callback();
        } finally {
            $this->app['env'] = $previous;
        }
    }

    private function roleUser(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_auto_login_is_blocked_in_production(): void
    {
        $this->withProductionEnv(function () {
            $this->assertFalse(SecurityEnvironment::allowsDevTools());
            $this->get('/auto-login')->assertNotFound();
        });
    }

    public function test_fake_pay_is_blocked_in_production(): void
    {
        if (! Schema::hasTable('online_payment_sessions')) {
            $this->markTestSkipped('Brak online_payment_sessions');
        }

        config(['payments.driver' => 'fake']);

        $event = Event::factory()->create();
        $session = OnlinePaymentSession::create([
            'driver' => 'fake',
            'status' => OnlinePaymentSession::STATUS_PENDING,
            'payable_type' => ContractPaymentSchedule::class,
            'payable_id' => 1,
            'event_id' => $event->id,
            'amount' => 100,
            'currency' => 'PLN',
            'expires_at' => now()->addDay(),
        ]);

        $this->withProductionEnv(function () use ($session) {
            $this->assertFalse(SecurityEnvironment::allowsFakePayments());
            $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
                ->post(route('payments.online.fake-pay', ['uuid' => $session->uuid]))
                ->assertNotFound();
        });
    }

    public function test_fake_pay_is_blocked_when_driver_is_not_fake(): void
    {
        if (! Schema::hasTable('online_payment_sessions')) {
            $this->markTestSkipped('Brak online_payment_sessions');
        }

        config(['payments.driver' => 'tpay']);

        $event = Event::factory()->create();
        $session = OnlinePaymentSession::create([
            'driver' => 'fake',
            'status' => OnlinePaymentSession::STATUS_PENDING,
            'payable_type' => ContractPaymentSchedule::class,
            'payable_id' => 1,
            'event_id' => $event->id,
            'amount' => 100,
            'currency' => 'PLN',
            'expires_at' => now()->addDay(),
        ]);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('payments.online.fake-pay', ['uuid' => $session->uuid]))
            ->assertNotFound();
    }

    public function test_agreement_demo_pay_does_not_mark_paid_in_production(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $model = Schema::hasTable('contracts') ? Contract::class : EventAgreement::class;
        /** @var Contract|EventAgreement $agreement */
        $agreement = $model::create([
            'event_id' => $event->id,
            'title' => 'Umowa test',
            'status' => 'signed',
            'payment_status' => 'pending',
            'amount_due' => 500,
            'currency' => 'PLN',
            'participant_count' => 1,
            'customer_name' => 'Jan Kowalski',
            'public_token' => (string) Str::uuid(),
            'created_by' => $user->id,
            'signed_at' => now(),
        ]);

        $this->withProductionEnv(function () use ($agreement) {
            config(['payments.driver' => 'tpay']);

            $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
                ->post(route('agreement.flow.pay', ['token' => $agreement->public_token]), [
                    'payment_method' => 'demo_transfer',
                    'accept_demo' => '1',
                ])->assertRedirect(route('agreement.flow.success', ['token' => $agreement->public_token]));

            $agreement->refresh();
            $this->assertSame('pending', $agreement->payment_status);
            $this->assertNull($agreement->paid_at);
            $this->assertTrue((bool) data_get($agreement->meta, 'flow.awaiting_offline_payment'));
        });
    }

    public function test_pilot_cannot_access_admin_insurance_export(): void
    {
        $pilot = $this->roleUser('pilot');
        $event = Event::factory()->create(['assigned_to' => $pilot->id]);

        $this->actingAs($pilot)
            ->get(route('admin.events.participants.insurance-export', ['event' => $event, 'format' => 'csv']))
            ->assertForbidden();
    }

    public function test_pilot_cannot_access_admin_event_pdf(): void
    {
        $pilot = $this->roleUser('pilot');
        $event = Event::factory()->create(['assigned_to' => $pilot->id]);

        $this->actingAs($pilot)
            ->get(route('admin.events.pdf', ['event' => $event, 'audience' => 'pilot']))
            ->assertForbidden();
    }

    public function test_api_pilot_cannot_see_other_events_or_mutate(): void
    {
        Role::firstOrCreate(['name' => 'pilot', 'guard_name' => 'web']);
        $pilot = User::factory()->create();
        $pilot->assignRole('pilot');

        $ownAttrs = ['assigned_to' => $pilot->id];
        if (Schema::hasColumn('events', 'shared_with_pilot')) {
            $ownAttrs['shared_with_pilot'] = true;
        }
        $own = Event::factory()->create($ownAttrs);
        $other = Event::factory()->create();

        $index = $this->actingAs($pilot, 'sanctum')
            ->json('GET', '/api/v1/events', [], ['Accept' => 'application/json']);

        $index->assertOk();
        $ids = collect($index->json('data.data'))->pluck('id')->all();
        $this->assertContains($own->id, $ids);
        $this->assertNotContains($other->id, $ids);

        $this->actingAs($pilot, 'sanctum')
            ->json('GET', '/api/v1/events/'.$other->id, [], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->actingAs($pilot, 'sanctum')
            ->json('POST', '/api/v1/events/'.$own->id.'/recalculate-price', [], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_import_export_panel_denies_non_admin(): void
    {
        $biuro = $this->roleUser('biuro');
        $this->actingAs($biuro);

        $this->assertFalse(ImportExportPanel::canAccess());
    }

    public function test_event_resource_can_view_any_denies_user_without_role(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertFalse(EventResource::canViewAny());
    }

    public function test_csv_export_requires_office_auth(): void
    {
        $this->get(route('events.export.csv'))->assertRedirect();

        $pilot = $this->roleUser('pilot');
        $this->actingAs($pilot)
            ->get(route('events.export.csv'))
            ->assertForbidden();
    }

    public function test_price_description_requires_auth(): void
    {
        $event = Event::factory()->create();

        $this->get(route('event.price-description.show', ['event' => $event]))
            ->assertRedirect();
    }

    public function test_token_default_abilities_are_not_wildcard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->json('POST', '/api/v1/auth/token', [
                'name' => 'mobile',
            ], [
                'Accept' => 'application/json',
                'Origin' => 'http://localhost:3000',
            ]);

        $response->assertCreated();
        $abilities = $response->json('data.abilities');
        $this->assertIsArray($abilities);
        $this->assertNotContains('*', $abilities);
    }
}
