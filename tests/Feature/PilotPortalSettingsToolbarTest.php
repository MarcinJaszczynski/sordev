<?php

namespace Tests\Feature;

use App\Livewire\PilotPortalSettingsToolbar;
use App\Models\Event;
use App\Models\User;
use App\Services\PilotOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotPortalSettingsToolbarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'pilot']);
    }

    public function test_revoke_pilot_sharing_clears_shared_flags(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $pilot = User::factory()->create(['status' => 'active']);

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'shared_with_pilot_at' => now(),
            'shared_with_pilot_by' => $admin->id,
            'pilot_trip_email_sent_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(PilotPortalSettingsToolbar::class, ['eventId' => $event->id])
            ->call('revokePilotSharing')
            ->assertHasNoErrors();

        $event->refresh();

        $this->assertFalse($event->shared_with_pilot);
        $this->assertNull($event->shared_with_pilot_at);
        $this->assertNull($event->shared_with_pilot_by);
        $this->assertNull($event->pilot_trip_email_sent_at);
        $this->assertNotContains($event->id, Event::query()->forPilot($pilot)->pluck('id')->all());
    }

    public function test_share_with_pilot_does_not_send_email_automatically(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $pilot = User::factory()->create([
            'status' => 'active',
            'email' => 'pilot.share@example.com',
        ]);

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => false,
        ]);

        $this->actingAs($admin);

        Livewire::test(PilotPortalSettingsToolbar::class, ['eventId' => $event->id])
            ->callAction('shareWithPilot')
            ->assertHasNoErrors();

        $event->refresh();

        $this->assertTrue($event->shared_with_pilot);
        $this->assertNotNull($event->shared_with_pilot_at);
        $this->assertNull($event->pilot_trip_email_sent_at);

        Mail::assertNothingSent();
    }

    public function test_send_pilot_email_action_records_sent_timestamp(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $pilot = User::factory()->create([
            'status' => 'active',
            'email' => 'pilot.share@example.com',
        ]);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'shared_with_pilot_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(PilotPortalSettingsToolbar::class, ['eventId' => $event->id])
            ->callAction('sendPilotEmail')
            ->assertHasNoErrors();

        $event->refresh();

        $this->assertNotNull($event->pilot_trip_email_sent_at);
        Mail::assertSent(\App\Mail\PilotTripSharedMail::class);
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

        Mail::assertSent(\App\Mail\PilotPanelAccessMail::class, function (\App\Mail\PilotPanelAccessMail $mail) use ($pilot) {
            return $mail->hasTo($pilot->email)
                && $mail->plainPassword === 'secret-pass';
        });
    }
}
