<?php

namespace Tests\Feature;

use App\Mail\PilotPanelAccessMail;
use App\Models\Currency;
use App\Models\Event;
use App\Models\PilotCashPreparation;
use App\Models\User;
use App\Services\PilotAdvanceService;
use App\Services\PilotOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Sprint2PilotFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'pilot']);
    }

    public function test_unshared_event_is_hidden_from_pilot_scope(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $hidden = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => false,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $visible = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $ids = Event::query()->forPilot($pilot)->pluck('id')->all();

        $this->assertNotContains($hidden->id, $ids);
        $this->assertContains($visible->id, $ids);
    }

    public function test_two_step_pilot_advance_syncs_cash_balance_on_approval(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'pilot_advance_planned_amount' => null,
            'pilot_funds_paid' => false,
        ]);

        app(PilotAdvanceService::class)->planAdvance($event, 1500.00);
        $event->refresh();

        $this->assertEquals(1500.0, (float) $event->pilot_advance_planned_amount);
        $this->assertFalse($event->pilot_funds_paid);

        Currency::query()->create([
            'name' => 'PLN',
            'code' => 'PLN',
            'symbol' => 'PLN',
            'exchange_rate' => 1,
        ]);

        app(PilotAdvanceService::class)->approvePayment($event->fresh());
        $event->refresh();

        $this->assertTrue($event->pilot_funds_paid);
        $this->assertNotNull($event->pilot_funds_paid_at);

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event);
        $this->assertSame($event->id, $settlement->event_id);

        $cash = PilotCashPreparation::query()
            ->where('settlement_id', $settlement->id)
            ->first();

        $this->assertNotNull($cash);
        $this->assertSame(1500.0, (float) $cash->provided_amount);
    }

    public function test_manual_panel_access_email_records_sent_timestamp(): void
    {
        Mail::fake();

        $pilot = User::factory()->create([
            'status' => 'active',
            'email' => 'new.pilot@example.com',
        ]);
        $pilot->assignRole('pilot');

        $admin = User::factory()->create(['status' => 'active']);

        app(PilotOnboardingService::class)->sendPanelAccessCredentials($pilot, 'secret-pass', $admin->id);

        $pilot->refresh();

        $this->assertNotNull($pilot->pilot_panel_access_sent_at);
        $this->assertSame($admin->id, $pilot->pilot_panel_access_sent_by);

        Mail::assertSent(PilotPanelAccessMail::class, function (PilotPanelAccessMail $mail) use ($pilot) {
            return $mail->hasTo($pilot->email)
                && $mail->plainPassword === 'secret-pass';
        });
    }

    public function test_pilot_trip_shared_mail_sent_with_event_context(): void
    {
        Mail::fake();

        $pilot = User::factory()->create([
            'status' => 'active',
            'email' => 'pilot.share@example.com',
        ]);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'code' => 'WAW-2026',
            'name' => 'Wycieczka testowa',
            'shared_with_pilot' => false,
        ]);

        Mail::to($pilot->email)->send(new \App\Mail\PilotTripSharedMail(
            $pilot,
            $event,
            url('/pilot/login'),
        ));

        Mail::assertSent(\App\Mail\PilotTripSharedMail::class, function (\App\Mail\PilotTripSharedMail $mail) use ($pilot, $event) {
            return $mail->hasTo($pilot->email)
                && $mail->event->is($event)
                && str_contains($mail->loginUrl, '/pilot/login');
        });
    }
}
