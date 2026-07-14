<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use App\Services\BankPayments\BankPaymentImportService;
use App\Services\BankPayments\MillenniumCsvParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BankPaymentImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_millennium_csv_parser_reads_incoming_transfers(): void
    {
        $csv = <<<'CSV'
Data operacji;Tytuł;Nadawca/Odbiorca;Numer rachunku;Kwota
17.06.2026;Rezerwacja nr UM-2026-001 Jan Kowalski;Jan Kowalski;12 3456 7890;1 250,00
17.06.2026;Wypłata biurowa;Biuro Podróży;12 3456 7890;-300,00
CSV;

        $rows = (new MillenniumCsvParser)->parse($csv);

        $this->assertCount(1, $rows);
        $this->assertSame('2026-06-17', $rows[0]['operation_date']);
        $this->assertSame(1250.0, $rows[0]['amount_pln']);
        $this->assertStringContainsString('UM-2026-001', $rows[0]['title']);
    }

    public function test_bank_import_matches_contract_and_applies_payment(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('admin');
        $user->assignRole('admin');

        $event = Event::factory()->create(['code' => 'WAW2026']);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Jan Kowalski',
            'booking_reference' => 'UM-2026-001',
            'due_amount_pln' => 1250,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        $contract = Contract::query()->create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa indywidualna',
            'contract_number' => 'UM-2026-001',
            'reservation_number' => 'UM-2026-001',
            'participant_payment_id' => $payment->id,
            'total_price' => 1250,
            'amount_paid' => 0,
            'status' => 'signed',
            'payment_status' => 'pending',
            'public_token' => 'token-'.uniqid(),
        ]);

        $csv = <<<'CSV'
Data operacji;Tytuł;Nadawca/Odbiorca;Numer rachunku;Kwota
17.06.2026;Rezerwacja nr UM-2026-001 Jan Kowalski;Jan Kowalski;12 3456 7890;1 250,00
CSV;

        $service = new BankPaymentImportService;
        $summary = $service->createPreviewFromCsv($csv, 'millennium.csv', $user->id);

        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['matched']);

        $batch = \App\Models\BankPaymentImportBatch::query()->findOrFail($summary['batch_id']);
        $lineIds = $batch->lines()->pluck('id')->all();

        $apply = $service->applyLines($batch, $lineIds);

        $this->assertSame(1, $apply['applied']);

        $contract->refresh();
        $payment->refresh()->load('entries');

        $this->assertSame(1250.0, (float) $contract->amount_paid);
        $this->assertSame('paid', $contract->payment_status);
        $this->assertSame(1250.0, (float) $payment->paid_amount_pln);
        $this->assertSame('paid', $payment->payment_status);

        if (\Illuminate\Support\Facades\Schema::hasTable('event_settlement_participant_payment_entries')) {
            $this->assertCount(1, $payment->entries);
            $this->assertSame(1250.0, (float) $payment->entries->first()->amount_pln);
            $this->assertSame('bank_import', $payment->entries->first()->source);
        }
    }

    public function test_bank_import_matches_pilot_advance_keyword(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('admin');
        $user->assignRole('admin');

        $event = Event::factory()->create([
            'code' => 'WAW-ZAL',
            'pilot_funds_paid' => false,
        ]);

        $csv = <<<'CSV'
Data operacji;Tytuł;Nadawca/Odbiorca;Numer rachunku;Kwota
18.06.2026;Zaliczka pilota WAW-ZAL;Biuro Podróży;12 3456 7890;1 500,00
CSV;

        $service = new BankPaymentImportService;
        $summary = $service->createPreviewFromCsv($csv, 'millennium.csv', $user->id);

        $this->assertSame(1, $summary['matched']);

        $batch = \App\Models\BankPaymentImportBatch::query()->findOrFail($summary['batch_id']);
        $line = $batch->lines()->firstOrFail();

        $this->assertSame('matched_pilot_advance', $line->match_status);
        $this->assertSame($event->id, $line->event_id);

        $apply = $service->applyLines($batch, [$line->id]);

        $this->assertSame(1, $apply['applied']);

        $event->refresh();

        $this->assertTrue($event->pilot_funds_paid);
        $this->assertNotNull($event->pilot_funds_paid_at);
    }

    public function test_event_scoped_import_panel_shows_only_matching_event_lines(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('admin');
        $user->assignRole('admin');
        $this->actingAs($user);

        $eventA = Event::factory()->create(['code' => 'EVT-A']);
        $eventB = Event::factory()->create(['code' => 'EVT-B']);
        $settlementA = EventSettlement::findOrCreateActiveForEvent($eventA);
        $settlementB = EventSettlement::findOrCreateActiveForEvent($eventB);

        $paymentA = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlementA->id,
            'participant_name' => 'Jan A',
            'booking_reference' => 'REF-A',
            'due_amount_pln' => 1000,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlementB->id,
            'participant_name' => 'Jan B',
            'booking_reference' => 'REF-B',
            'due_amount_pln' => 2000,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        Contract::query()->create([
            'event_id' => $eventA->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa A',
            'contract_number' => 'REF-A',
            'reservation_number' => 'REF-A',
            'participant_payment_id' => $paymentA->id,
            'total_price' => 1000,
            'amount_paid' => 0,
            'status' => 'signed',
            'payment_status' => 'pending',
            'public_token' => 'token-a-'.uniqid(),
        ]);

        Contract::query()->create([
            'event_id' => $eventB->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa B',
            'contract_number' => 'REF-B',
            'reservation_number' => 'REF-B',
            'total_price' => 2000,
            'amount_paid' => 0,
            'status' => 'signed',
            'payment_status' => 'pending',
            'public_token' => 'token-b-'.uniqid(),
        ]);

        $csv = <<<'CSV'
Data operacji;Tytuł;Nadawca/Odbiorca;Numer rachunku;Kwota
17.06.2026;Rezerwacja nr REF-A Jan A;Jan A;12 3456 7890;1 000,00
17.06.2026;Rezerwacja nr REF-B Jan B;Jan B;12 3456 7890;2 000,00
CSV;

        $service = new BankPaymentImportService;
        $summary = $service->createPreviewFromCsv($csv, 'millennium.csv', $user->id);

        $this->assertSame(2, $summary['total']);

        $component = \Livewire\Livewire::test(\App\Livewire\EventBankPaymentImportPanel::class, [
            'eventId' => $eventA->id,
        ]);

        $component->set('batchId', $summary['batch_id']);
        $component->call('loadPreviewLines');

        $component->assertSet('hiddenOtherEventsCount', 1);
        $this->assertCount(1, $component->get('previewLines'));
        $this->assertStringContainsString('REF-A', (string) $component->get('previewLines')[0]['title']);
    }
}
