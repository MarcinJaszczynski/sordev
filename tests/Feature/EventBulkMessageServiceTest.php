<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventMessageLog;
use App\Models\EventParticipant;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\MailTemplate;
use App\Services\EventBulkMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventBulkMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_consents_segment_creates_message_log(): void
    {
        if (! Schema::hasTable('event_message_logs') || ! Schema::hasTable('event_participants')) {
            $this->markTestSkipped('Brak tabel komunikacji / uczestników.');
        }

        Mail::fake();

        $event = Event::factory()->create();

        EventParticipant::query()->create([
            'event_id' => $event->id,
            'first_name' => 'Bez',
            'last_name' => 'Zgody',
            'email' => 'bez-zgody@example.com',
            'source' => EventParticipant::SOURCE_MANUAL,
            'status' => EventParticipant::STATUS_ACTIVE,
            'consents' => null,
        ]);

        EventParticipant::query()->create([
            'event_id' => $event->id,
            'first_name' => 'Ze',
            'last_name' => 'Zgoda',
            'email' => 'ze-zgoda@example.com',
            'source' => EventParticipant::SOURCE_MANUAL,
            'status' => EventParticipant::STATUS_ACTIVE,
            'parent_consent_at' => now(),
            'consents' => [
                'terms' => now()->toIso8601String(),
                'insurance' => now()->toIso8601String(),
                'rodo' => now()->toIso8601String(),
            ],
        ]);

        $result = app(EventBulkMessageService::class)->send($event, EventMessageLog::SEGMENT_MISSING_CONSENTS);

        $this->assertSame(1, $result['recipients']);
        $this->assertNotNull($result['log']);
        $this->assertSame(EventMessageLog::SEGMENT_MISSING_CONSENTS, $result['log']->segment);
        $this->assertSame(MailTemplate::KEY_EVENT_BULK_NOTICE, $result['log']->mail_template_key);
    }

    public function test_overdue_requires_past_installment_due_date(): void
    {
        if (! Schema::hasTable('event_participants') || ! Schema::hasTable('contracts')) {
            $this->markTestSkipped('Brak tabel uczestników / umów.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Zaległy',
            'due_amount_pln' => 500,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        $contract = \App\Models\Contract::create([
            'event_id' => $event->id,
            'contract_type' => \App\Models\Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa zaległa',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'total_price' => 500,
            'amount_paid' => 0,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'participant_payment_id' => $payment->id,
            'created_by' => \App\Models\User::factory()->create()->id,
        ]);

        \App\Models\ContractPaymentSchedule::create([
            'contract_id' => $contract->id,
            'sort_order' => 1,
            'label' => 'Zaliczka',
            'amount' => 500,
            'paid_amount' => 0,
            'due_date' => now()->subDays(3)->toDateString(),
        ]);

        $participant = EventParticipant::query()->create([
            'event_id' => $event->id,
            'first_name' => 'Zaległy',
            'last_name' => 'Uczeń',
            'email' => 'zalegly@example.com',
            'source' => EventParticipant::SOURCE_MANUAL,
            'status' => EventParticipant::STATUS_ACTIVE,
            'contract_id' => $contract->id,
            'participant_payment_id' => $payment->id,
        ]);

        $recipients = app(EventBulkMessageService::class)->recipients($event, EventMessageLog::SEGMENT_OVERDUE);

        $this->assertTrue($recipients->contains(fn (EventParticipant $p) => $p->id === $participant->id));
    }
}
