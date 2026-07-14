<?php

namespace Tests\Feature;

use App\Mail\ParticipantPaymentReminderMail;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use App\Services\ParticipantPaymentReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParticipantPaymentReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_reminder_queues_mail_when_participant_has_email(): void
    {
        Mail::fake();

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create(['name' => 'Wycieczka testowa']);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Susan Dale',
            'due_amount_pln' => 1500,
            'paid_amount_pln' => 150,
            'payment_status' => 'partial',
        ]);

        EventParticipant::query()->create([
            'event_id' => $event->id,
            'first_name' => 'Susan',
            'last_name' => 'Dale',
            'email' => 'susan.dale@example.com',
            'participant_payment_id' => $payment->id,
            'source' => EventParticipant::SOURCE_MANUAL,
            'status' => EventParticipant::STATUS_ACTIVE,
        ]);

        $result = app(ParticipantPaymentReminderService::class)->sendReminder($payment->fresh(['eventParticipant']));

        $this->assertTrue($result['sent']);
        Mail::assertQueued(ParticipantPaymentReminderMail::class, function (ParticipantPaymentReminderMail $mail): bool {
            return $mail->hasTo('susan.dale@example.com');
        });
    }

    public function test_reminder_skips_when_email_missing(): void
    {
        Mail::fake();

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Bez Emaila',
            'due_amount_pln' => 1500,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        $result = app(ParticipantPaymentReminderService::class)->sendReminder($payment);

        $this->assertFalse($result['sent']);
        Mail::assertNothingQueued();
    }
}
