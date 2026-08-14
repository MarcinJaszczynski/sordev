<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Crm\AnswerClientTripInquiryAction;
use App\Actions\Crm\CreateClientTripInquiryAction;
use App\Data\CreateClientTripInquiryData;
use App\Mail\ClientTripInquiryAdminMail;
use App\Models\ClientTripInquiry;
use App\Models\Event;
use App\Models\User;
use App\Services\ClientTripInquiryOfficeNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OfficeCaretakerPortalInquiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('client_trip_inquiries') || ! Schema::hasColumn('events', 'office_caretaker_id')) {
            $this->markTestSkipped('Brak kolumn opiekuna / eskalacji.');
        }

        foreach (['admin', 'super_admin', 'biuro', 'client_participant'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_inquiry_with_caretaker_mails_only_caretaker_and_stays_unescalated(): void
    {
        Mail::fake();

        $caretaker = User::factory()->create([
            'status' => 'active',
            'email' => 'opiekun@test.local',
        ]);
        $caretaker->assignRole('admin');

        $other = User::factory()->create([
            'status' => 'active',
            'email' => 'inny@test.local',
        ]);
        $other->assignRole('biuro');

        $client = User::factory()->create(['status' => 'active', 'email' => 'klient@test.local']);
        $event = Event::factory()->create(['office_caretaker_id' => $caretaker->id]);

        $inquiry = app(CreateClientTripInquiryAction::class)(new CreateClientTripInquiryData(
            event: $event,
            user: $client,
            subject: 'Pytanie do opiekuna',
            body: 'Czy wyjazd jest aktualny?',
        ));

        $this->assertNull($inquiry->fresh()->escalated_at);

        Mail::assertSent(ClientTripInquiryAdminMail::class, function (ClientTripInquiryAdminMail $mail) use ($caretaker): bool {
            return $mail->hasTo($caretaker->email);
        });

        Mail::assertNotSent(ClientTripInquiryAdminMail::class, function (ClientTripInquiryAdminMail $mail) use ($other): bool {
            return $mail->hasTo($other->email);
        });

        $this->assertTrue(
            ClientTripInquiry::query()->visibleToOfficeUser($caretaker)->whereKey($inquiry->id)->exists()
        );
        $this->assertFalse(
            ClientTripInquiry::query()->visibleToOfficeUser($other)->whereKey($inquiry->id)->exists()
        );
    }

    public function test_inquiry_without_caretaker_escalates_immediately_to_office_mail(): void
    {
        Mail::fake();
        config(['mail.inquiries_to' => 'biuro@test.local']);

        $client = User::factory()->create(['status' => 'active', 'email' => 'klient@test.local']);
        $event = Event::factory()->create(['office_caretaker_id' => null]);

        $inquiry = app(CreateClientTripInquiryAction::class)(new CreateClientTripInquiryData(
            event: $event,
            user: $client,
            subject: 'Ogólne pytanie',
            body: 'Treść',
        ));

        $this->assertNotNull($inquiry->fresh()->escalated_at);

        Mail::assertSent(ClientTripInquiryAdminMail::class, function (ClientTripInquiryAdminMail $mail): bool {
            return $mail->hasTo('biuro@test.local');
        });
    }

    public function test_escalation_after_24h_makes_inquiry_visible_to_whole_office(): void
    {
        Mail::fake();
        config(['mail.inquiries_to' => 'biuro@test.local']);

        $caretaker = User::factory()->create([
            'status' => 'active',
            'email' => 'opiekun@test.local',
        ]);
        $caretaker->assignRole('admin');

        $colleague = User::factory()->create([
            'status' => 'active',
            'email' => 'kolega@test.local',
        ]);
        $colleague->assignRole('biuro');

        $client = User::factory()->create(['status' => 'active']);
        $event = Event::factory()->create(['office_caretaker_id' => $caretaker->id]);

        $inquiry = ClientTripInquiry::query()->create([
            'event_id' => $event->id,
            'user_id' => $client->id,
            'subject' => 'Stare pytanie',
            'body' => 'Bez odpowiedzi',
            'status' => ClientTripInquiry::STATUS_OPEN,
        ]);
        ClientTripInquiry::query()->whereKey($inquiry->id)->update([
            'created_at' => now()->subHours(25),
            'updated_at' => now()->subHours(25),
        ]);
        $inquiry->refresh();

        $this->assertFalse(
            ClientTripInquiry::query()->visibleToOfficeUser($colleague)->whereKey($inquiry->id)->exists()
        );

        $count = app(ClientTripInquiryOfficeNotifier::class)->escalateDue();
        $this->assertSame(1, $count);
        $this->assertNotNull($inquiry->fresh()->escalated_at);

        Mail::assertSent(ClientTripInquiryAdminMail::class, function (ClientTripInquiryAdminMail $mail): bool {
            return $mail->hasTo('biuro@test.local');
        });

        $this->assertTrue(
            ClientTripInquiry::query()->visibleToOfficeUser($colleague)->whereKey($inquiry->id)->exists()
        );
    }

    public function test_changing_caretaker_notifies_new_person_without_resetting_deadline(): void
    {
        Mail::fake();

        $first = User::factory()->create([
            'status' => 'active',
            'email' => 'pierwszy@test.local',
        ]);
        $first->assignRole('admin');

        $second = User::factory()->create([
            'status' => 'active',
            'email' => 'drugi@test.local',
        ]);
        $second->assignRole('biuro');

        $client = User::factory()->create(['status' => 'active']);
        $event = Event::factory()->create(['office_caretaker_id' => $first->id]);

        $createdAt = now()->subHours(10)->startOfSecond();
        $inquiry = ClientTripInquiry::query()->create([
            'event_id' => $event->id,
            'user_id' => $client->id,
            'subject' => 'Przejęcie',
            'body' => 'Treść',
            'status' => ClientTripInquiry::STATUS_OPEN,
        ]);
        ClientTripInquiry::query()->whereKey($inquiry->id)->update([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $inquiry->refresh();

        $event->update(['office_caretaker_id' => $second->id]);

        Mail::assertSent(ClientTripInquiryAdminMail::class, function (ClientTripInquiryAdminMail $mail) use ($second): bool {
            return $mail->hasTo($second->email);
        });

        $fresh = $inquiry->fresh();
        $this->assertNull($fresh->escalated_at);
        $this->assertTrue($fresh->created_at->equalTo($createdAt));

        $this->assertTrue(
            ClientTripInquiry::query()->visibleToOfficeUser($second)->whereKey($inquiry->id)->exists()
        );
        $this->assertFalse(
            ClientTripInquiry::query()->visibleToOfficeUser($first)->whereKey($inquiry->id)->exists()
        );
    }

    public function test_answered_inquiry_is_not_escalated(): void
    {
        Mail::fake();

        $caretaker = User::factory()->create([
            'status' => 'active',
            'email' => 'opiekun@test.local',
        ]);
        $caretaker->assignRole('admin');

        $client = User::factory()->create(['status' => 'active', 'email' => 'klient@test.local']);
        $event = Event::factory()->create(['office_caretaker_id' => $caretaker->id]);

        $inquiry = ClientTripInquiry::query()->create([
            'event_id' => $event->id,
            'user_id' => $client->id,
            'subject' => 'Już obsłużone',
            'body' => 'Treść',
            'status' => ClientTripInquiry::STATUS_OPEN,
        ]);
        ClientTripInquiry::query()->whereKey($inquiry->id)->update([
            'created_at' => now()->subHours(30),
            'updated_at' => now()->subHours(30),
        ]);
        $inquiry->refresh();

        app(AnswerClientTripInquiryAction::class)($inquiry, 'Odpowiedź', $caretaker);

        $count = app(ClientTripInquiryOfficeNotifier::class)->escalateDue();
        $this->assertSame(0, $count);
        $this->assertNull($inquiry->fresh()->escalated_at);
    }
}
