<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Finance\RecordParticipantPaymentAction;
use App\Actions\Finance\RecordSettlementCostPaymentAction;
use App\Data\RecordParticipantPaymentData;
use App\Data\RecordSettlementCostPaymentData;
use App\Models\ClientInvoiceRequest;
use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventSettlementParticipantPaymentEntry;
use App\Models\OnlinePaymentSession;
use App\Models\PilotCashPreparation;
use App\Models\PilotCurrencyExchange;
use App\Models\Reservation;
use App\Models\User;
use App\Models\VendorInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Uzupełnia imprezę o zróżnicowane dane finansowe pod ręczne testy P0.
 * Idempotentne względem markera [finance-demo] w notes / document_number.
 */
class SeedEventFinanceDemoCommand extends Command
{
    protected $signature = 'events:seed-finance-demo
        {event=19 : ID imprezy}
        {--force-status : Ustaw status imprezy na confirmed (jeśli nadal inquiry/offer)}';

    protected $description = 'Seed zróżnicowanych danych finance/umów/uczestników pod checklistę P0 (domyślnie event 19 — Paryż)';

    private const MARKER = '[finance-demo]';

    public function handle(
        RecordParticipantPaymentAction $recordParticipantPayment,
        RecordSettlementCostPaymentAction $recordCostPayment,
    ): int {
        $eventId = (int) $this->argument('event');
        $event = Event::query()->find($eventId);

        if (! $event) {
            $this->components->error("Brak imprezy #{$eventId}");

            return self::FAILURE;
        }

        Gate::before(fn () => true);
        $actor = User::query()->whereKey(14)->first()
            ?? User::query()->where('email', 'pilot@test.local')->first()
            ?? User::query()->orderBy('id')->first();
        if ($actor) {
            Auth::login($actor);
        }

        $summary = DB::transaction(function () use ($event, $recordParticipantPayment, $recordCostPayment, $actor): array {
            if ($this->option('force-status') || in_array($event->status, [Event::STATUS_INQUIRY, Event::STATUS_OFFER], true)) {
                $event->update(['status' => Event::STATUS_CONFIRMED]);
            }

            if (Schema::hasColumn('events', 'shared_with_pilot') && blank($event->shared_with_pilot)) {
                $event->update([
                    'shared_with_pilot' => true,
                    'shared_with_pilot_at' => now(),
                    'assigned_to' => $event->assigned_to ?: $actor?->id,
                ]);
            }

            $settlement = EventSettlement::findOrCreateActiveForEvent($event);
            if ($settlement->status === 'draft') {
                $settlement->update(['status' => 'active']);
            }
            if (! $settlement->pilot_id && $actor) {
                $settlement->update(['pilot_id' => $actor->id]);
            }

            $plnId = Currency::defaultPlnId() ?? 1;
            $eurId = (int) (Currency::query()->where('name', 'like', '%Euro%')->value('id') ?? 2);

            $participants = $this->seedParticipants($event, $settlement, $recordParticipantPayment);
            $contracts = $this->seedContracts($event, $participants);
            $costPayments = $this->seedCostPaymentsAndInbox($settlement, $recordCostPayment, $actor?->id);
            $docs = $this->seedSettlementDocuments($settlement, $plnId);
            $pilot = $this->seedPilotCashAndFx($settlement, $plnId, $eurId, $actor?->id);
            $reservation = $this->seedReservation($event, $settlement);
            $vendor = $this->seedVendorInvoice($event);
            $invoiceReq = $this->seedClientInvoiceRequest($event, $contracts[0] ?? null, $actor?->id);
            $online = $this->seedOnlinePaymentSession($event, $contracts[0] ?? null);

            $settlement->recalculateTotals();
            $settlement->recalculatePilotCash();

            return [
                'event' => $event->fresh(),
                'settlement_id' => $settlement->id,
                'participants' => count($participants),
                'contracts' => count($contracts),
                'cost_payments' => $costPayments,
                'settlement_docs' => $docs,
                'pilot' => $pilot,
                'reservation' => $reservation,
                'vendor_invoice' => $vendor,
                'client_invoice_request' => $invoiceReq,
                'online_session' => $online,
            ];
        });

        $this->newLine();
        $this->components->info('Dane demo finance gotowe: '.$summary['event']->name.' (#'.$summary['event']->id.')');
        $this->table(
            ['Pole', 'Wartość'],
            [
                ['Status imprezy', $summary['event']->status],
                ['Settlement ID', (string) $summary['settlement_id']],
                ['Uczestnicy (demo / łącznie nowe)', (string) $summary['participants']],
                ['Umowy + raty', (string) $summary['contracts']],
                ['Wpłaty kosztowe / inbox', (string) $summary['cost_payments']],
                ['Dok. rozliczenia', (string) $summary['settlement_docs']],
                ['Gotówka / FX', $summary['pilot']],
                ['Rezerwacja', $summary['reservation']],
                ['Faktura zakupowa', $summary['vendor_invoice']],
                ['Wniosek o FV', $summary['client_invoice_request']],
                ['Sesja online', $summary['online_session']],
                ['Finanse UI', url('/admin/events/'.$summary['event']->id.'/finance')],
            ],
        );

        $this->components->bulletList([
            'P0-01…P0-09 — Finanse imprezy (koszty, wpłaty, gotówka, dokumenty)',
            'P0-16 — Skrzynka płatności (zaległe koszty z due_date)',
            'P0-17 — Rejestr wpłat (ledger uczestników)',
            'P0-19 — Vendor invoices / KSeF inbox',
            'P0-20 — Wnioski o fakturę',
            'P0-21 — OnlinePaymentSession pending + raty umowy',
            'P1-07 — Rezerwacja na punkcie programu',
        ]);

        return self::SUCCESS;
    }

    /**
     * @return list<EventParticipant>
     */
    private function seedParticipants(
        Event $event,
        EventSettlement $settlement,
        RecordParticipantPaymentAction $recordPayment,
    ): array {
        $defs = [
            ['Anna', 'Kowalska', 2600.0, 2600.0, 'paid', 'transfer'],
            ['Piotr', 'Nowak', 2600.0, 800.0, 'partial', 'transfer'],
            ['Maria', 'Wiśniewska', 2600.0, 0.0, 'pending', null],
            ['Jan', 'Zieliński', 2600.0, 2800.0, 'overpaid', 'cash'],
            ['Ewa', 'Dąbrowska', 1300.0, 650.0, 'partial', 'card'],
        ];

        $created = [];

        foreach ($defs as [$first, $last, $due, $paid, $status, $method]) {
            $fullName = "{$first} {$last}";
            $existing = EventParticipant::query()
                ->where('event_id', $event->id)
                ->where('first_name', $first)
                ->where('last_name', $last)
                ->first();

            if ($existing) {
                $created[] = $existing;

                continue;
            }

            $payment = EventSettlementParticipantPayment::query()->create([
                'settlement_id' => $settlement->id,
                'participant_name' => $fullName,
                'booking_reference' => 'DEMO-'.Str::upper(Str::random(6)),
                'due_amount_pln' => $due,
                'paid_amount_pln' => 0,
                'discount_amount_pln' => 0,
                'payment_status' => 'pending',
                'notes' => self::MARKER.' uczestnik demo',
                'approval_status' => 'approved',
            ]);

            $participant = EventParticipant::query()->create([
                'event_id' => $event->id,
                'first_name' => $first,
                'last_name' => $last,
                'email' => Str::slug($first).'.'.Str::slug($last).'.demo19@example.test',
                'phone' => '+48 500 '.random_int(100, 999).' '.random_int(100, 999),
                'source' => EventParticipant::SOURCE_MANUAL,
                'status' => EventParticipant::STATUS_ACTIVE,
                'participant_payment_id' => $payment->id,
                'notes' => self::MARKER,
            ]);

            if ($paid > 0) {
                ($recordPayment)(new RecordParticipantPaymentData(
                    payment: $payment->fresh(),
                    amount: $paid,
                    paidAt: now()->subDays(random_int(1, 14)),
                    paymentMethod: $method,
                    notes: self::MARKER.' ledger',
                    payerName: $fullName,
                    source: EventSettlementParticipantPaymentEntry::SOURCE_MANUAL,
                    paymentKind: EventSettlementParticipantPaymentEntry::KIND_REGULAR,
                ));
            }

            $created[] = $participant->fresh();
        }

        return $created;
    }

    /**
     * @param  list<EventParticipant>  $participants
     * @return list<Contract>
     */
    private function seedContracts(Event $event, array $participants): array
    {
        if (! Schema::hasTable('contracts')) {
            return [];
        }

        $existing = Contract::query()
            ->where('event_id', $event->id)
            ->where('admin_notes', 'like', '%'.self::MARKER.'%')
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing->all();
        }

        $pick = array_slice($participants, 0, 3);
        $contracts = [];

        foreach ($pick as $index => $participant) {
            $unit = 2600.0;
            $total = $unit;
            $contract = Contract::query()->create([
                'event_id' => $event->id,
                'contract_type' => Contract::TYPE_INDIVIDUAL,
                'participant_payment_id' => $participant->participant_payment_id,
                'title' => 'Umowa demo — '.$participant->first_name.' '.$participant->last_name,
                'contract_number' => 'DEMO/19/'.($index + 1),
                'contract_date' => now()->subDays(20)->toDateString(),
                'event_name' => $event->name,
                'event_start_date' => $event->start_date,
                'event_end_date' => $event->end_date,
                'customer_name' => 'Opiekun '.$participant->last_name,
                'customer_email' => $participant->email,
                'customer_phone' => $participant->phone,
                'participant_name' => trim($participant->first_name.' '.$participant->last_name),
                'participant_email' => $participant->email,
                'participant_phone' => $participant->phone,
                'participant_count' => 1,
                'unit_price' => $unit,
                'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
                'total_price' => $total,
                'amount_paid' => $index === 0 ? 1000 : 0,
                'currency' => 'PLN',
                'status' => 'signed',
                'payment_status' => $index === 0 ? 'partial' : 'pending',
                'public_token' => Str::random(48),
                'body_edit_mode' => Contract::BODY_EDIT_MANUAL,
                'agreement_body' => '<p>Umowa demo '.self::MARKER.'</p>',
                'admin_notes' => self::MARKER.' umowa testowa P0',
            ]);

            if (Schema::hasColumn('event_participants', 'contract_id')) {
                $participant->update(['contract_id' => $contract->id]);
            }

            ContractPaymentSchedule::query()->create([
                'contract_id' => $contract->id,
                'sort_order' => 1,
                'label' => 'Zaliczka',
                'amount' => 1000,
                'currency_code' => 'PLN',
                'paid_amount' => $index === 0 ? 1000 : 0,
                'paid_at' => $index === 0 ? now()->subDays(10) : null,
                'due_date' => now()->subDays(5)->toDateString(),
                'notes' => self::MARKER,
            ]);

            ContractPaymentSchedule::query()->create([
                'contract_id' => $contract->id,
                'sort_order' => 2,
                'label' => 'Dopłata',
                'amount' => 1600,
                'currency_code' => 'PLN',
                'paid_amount' => 0,
                'due_date' => now()->addDays(7)->toDateString(),
                'notes' => self::MARKER,
            ]);

            $contracts[] = $contract;
        }

        return $contracts;
    }

    private function seedCostPaymentsAndInbox(
        EventSettlement $settlement,
        RecordSettlementCostPaymentAction $recordCostPayment,
        ?int $userId,
    ): string {
        $plan = $settlement->costs()
            ->whereNull('deleted_at')
            ->where('source_type', 'program_point')
            ->where('payment_status', 'planned')
            ->where('planned_amount_pln', '>', 100)
            ->orderByDesc('planned_amount_pln')
            ->first();

        $manualInbox = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('notes', 'like', '%'.self::MARKER.' inbox%')
            ->first();

        if (! $manualInbox) {
            $manualInbox = $settlement->costs()->create([
                'source_type' => 'manual',
                'name' => 'Hotel Paryż — zaliczka (demo)',
                'planned_amount' => 4500,
                'planned_currency_id' => Currency::defaultPlnId() ?? 1,
                'planned_convert_to_pln' => true,
                'planned_rate' => 1,
                'planned_amount_pln' => 4500,
                'paid_by' => 'office',
                'advance_type' => 'advance',
                'payment_method' => 'transfer',
                'payment_status' => 'advance_required',
                'advance_due_date' => now()->subDays(2),
                'advance_amount' => 1500,
                'notes' => self::MARKER.' inbox zaległa płatność',
                'order' => 9001,
                'contractor_id' => 2,
            ]);
        }

        $partial = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('notes', 'like', '%'.self::MARKER.' partial-inbox%')
            ->first();

        if (! $partial) {
            $partial = $settlement->costs()->create([
                'source_type' => 'manual',
                'name' => 'Muzeum — rezerwacja (demo)',
                'planned_amount' => 2200,
                'planned_currency_id' => Currency::defaultPlnId() ?? 1,
                'planned_convert_to_pln' => true,
                'planned_rate' => 1,
                'planned_amount_pln' => 2200,
                'actual_amount' => 800,
                'actual_currency_id' => Currency::defaultPlnId() ?? 1,
                'actual_rate' => 1,
                'actual_amount_pln' => 800,
                'paid_by' => 'office',
                'advance_type' => 'advance',
                'payment_method' => 'transfer',
                'payment_status' => 'partially_paid',
                'advance_due_date' => now()->addDays(3),
                'notes' => self::MARKER.' partial-inbox',
                'order' => 9002,
                'contractor_id' => 1,
            ]);
        }

        $paymentNote = '';
        if ($plan) {
            $already = $settlement->costs()
                ->where('notes', 'like', '%'.self::MARKER.' cost-payment%')
                ->exists();

            if (! $already) {
                ($recordCostPayment)(new RecordSettlementCostPaymentData(
                    planCost: $plan,
                    amountPln: min(500.0, (float) $plan->planned_amount_pln / 2),
                    paymentMethod: 'transfer',
                    paidBy: 'office',
                    advanceType: 'advance',
                    paidAt: now()->subDays(3),
                    documentNumber: 'FV-DEMO-19/1',
                    notes: self::MARKER.' cost-payment',
                    paidByUserId: $userId,
                ));
                $paymentNote = ' + wpłata do planu #'.$plan->id;
            }
        }

        return 'inbox #'.$manualInbox->id.', partial #'.$partial->id.$paymentNote;
    }

    private function seedSettlementDocuments(EventSettlement $settlement, int $plnId): string
    {
        $existing = EventSettlementDocument::query()
            ->where('settlement_id', $settlement->id)
            ->where('notes', 'like', '%'.self::MARKER.'%')
            ->count();

        if ($existing >= 2) {
            return (string) $existing.' (już było)';
        }

        $costIds = $settlement->costs()
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->limit(2)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        EventSettlementDocument::query()->create([
            'settlement_id' => $settlement->id,
            'document_type' => 'invoice',
            'document_number' => 'FV/DEMO/19/01',
            'vendor_name' => 'Hotel Paris Demo SARL',
            'total_amount' => 4500,
            'currency_id' => $plnId,
            'issue_date' => now()->subDays(4)->toDateString(),
            'payment_date' => now()->subDays(1),
            'payment_method' => 'transfer',
            'payer_scope' => 'office',
            'linked_cost_ids' => $costIds,
            'files' => [],
            'notes' => self::MARKER.' dokument rozliczenia',
            'approval_status' => 'approved',
            'created_by' => Auth::id(),
        ]);

        EventSettlementDocument::query()->create([
            'settlement_id' => $settlement->id,
            'document_type' => 'receipt',
            'document_number' => 'PARAGON-DEMO-19',
            'vendor_name' => 'Kawiarnia Demo',
            'total_amount' => 86.50,
            'currency_id' => $plnId,
            'issue_date' => now()->subDay()->toDateString(),
            'payment_method' => 'cash',
            'payer_scope' => 'pilot',
            'linked_cost_ids' => [],
            'files' => [],
            'notes' => self::MARKER.' paragon pilota',
            'approval_status' => 'pending',
            'created_by' => Auth::id(),
        ]);

        return '2 utworzone';
    }

    private function seedPilotCashAndFx(
        EventSettlement $settlement,
        int $plnId,
        int $eurId,
        ?int $userId,
    ): string {
        $cash = PilotCashPreparation::query()
            ->where('settlement_id', $settlement->id)
            ->where(function ($query) use ($eurId): void {
                $query->where('notes', 'like', '%'.self::MARKER.'%')
                    ->orWhere('currency_id', $eurId);
            })
            ->first();

        if (! $cash) {
            $cash = PilotCashPreparation::query()->create([
                'settlement_id' => $settlement->id,
                'currency_id' => $eurId,
                'calculated_amount' => 800,
                'approved_amount' => 800,
                'provided_amount' => 800,
                'spent_amount' => 120,
                'returned_amount' => 0,
                'balance' => 680,
                'rate_used' => 4.25,
                'pln_equivalent' => 3400,
                'status' => 'provided',
                'provided_at' => now()->subDays(2),
                'notes' => self::MARKER.' gotówka EUR dla pilota',
                'approval_status' => 'approved',
            ]);
        } elseif (! str_contains((string) $cash->notes, self::MARKER)) {
            $cash->update([
                'provided_amount' => $cash->provided_amount ?: 800,
                'spent_amount' => $cash->spent_amount ?: 120,
                'balance' => $cash->balance ?: 680,
                'status' => $cash->status === 'calculated' ? 'provided' : $cash->status,
                'notes' => trim((string) $cash->notes.' '.self::MARKER.' gotówka EUR dla pilota'),
            ]);
        }

        $fx = PilotCurrencyExchange::query()
            ->where('settlement_id', $settlement->id)
            ->where('notes', 'like', '%'.self::MARKER.'%')
            ->first();

        if (! $fx) {
            PilotCurrencyExchange::query()->create([
                'settlement_id' => $settlement->id,
                'from_currency_id' => $plnId,
                'to_currency_id' => $eurId,
                'from_amount' => 850,
                'to_amount' => 200,
                'exchange_rate' => 4.25,
                'rate_difference_pln' => 0,
                'exchanged_at' => now()->subDays(2),
                'notes' => self::MARKER.' wymiana PLN→EUR',
                'created_by' => $userId,
            ]);
        }

        return 'cash #'.$cash->id;
    }

    private function seedReservation(Event $event, EventSettlement $settlement): string
    {
        $existing = Reservation::query()
            ->where('event_id', $event->id)
            ->where('notes', 'like', '%'.self::MARKER.'%')
            ->first();

        if ($existing) {
            return '#'.$existing->id.' (już było)';
        }

        $point = $event->programPoints()->orderBy('day')->orderBy('order')->first();
        $cost = $settlement->costs()
            ->where('source_type', 'program_point')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();

        $reservation = Reservation::query()->create([
            'event_id' => $event->id,
            'program_point_id' => $point?->id,
            'settlement_cost_id' => $cost?->id,
            'contractor_id' => 2,
            'booking_reference' => 'RES-DEMO-19',
            'participant_count' => (int) ($event->participant_count ?: 30),
            'reserved_amount' => 4500,
            'currency_id' => Currency::defaultPlnId() ?? 1,
            'convert_to_pln' => true,
            'amount_basis' => 'lump_sum',
            'status' => 'confirmed',
            'reserved_at' => now()->subDays(8),
            'confirmed_at' => now()->subDays(5)->toDateString(),
            'deposit_due_at' => now()->subDays(3)->toDateString(),
            'deposit_paid_at' => now()->subDays(2)->toDateString(),
            'notes' => self::MARKER.' rezerwacja hotelu',
            'office_notes' => self::MARKER,
            'created_by' => Auth::id(),
        ]);

        return '#'.$reservation->id;
    }

    private function seedVendorInvoice(Event $event): string
    {
        $existing = VendorInvoice::query()
            ->where('event_id', $event->id)
            ->where('notes', 'like', '%'.self::MARKER.'%')
            ->first();

        if ($existing) {
            return '#'.$existing->id.' (już było)';
        }

        $invoice = VendorInvoice::query()->create([
            'source' => 'manual',
            'invoice_number' => 'FV-KSEF-DEMO-19',
            'ksef_number' => 'DEMO-KSEF-19-001',
            'issue_date' => now()->subDays(3)->toDateString(),
            'sale_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'received_date' => now()->subDays(1)->toDateString(),
            'currency' => 'PLN',
            'net_amount' => 3658.54,
            'vat_amount' => 841.46,
            'gross_amount' => 4500,
            'paid_amount' => 0,
            'payment_status' => 'due',
            'payment_method' => 'transfer',
            'approval_status' => 'pending',
            'matching_status' => 'unmatched',
            'seller_nip' => '5252345678',
            'seller_name' => 'Hotel Paris Demo SARL',
            'seller_city' => 'Paris',
            'seller_country' => 'FR',
            'buyer_name' => 'SOR Demo',
            'contractor_id' => 2,
            'event_id' => $event->id,
            'sync_to_settlement' => false,
            'notes' => self::MARKER.' faktura zakupowa / KSeF smoke',
        ]);

        return '#'.$invoice->id;
    }

    private function seedClientInvoiceRequest(Event $event, ?Contract $contract, ?int $userId): string
    {
        $ids = [];

        $variants = [
            [
                'key' => 'portal-company',
                'event_id' => $event->id,
                'contract_id' => $contract?->id,
                'user_id' => $userId,
                'buyer_type' => ClientInvoiceRequest::BUYER_COMPANY,
                'source' => ClientInvoiceRequest::SOURCE_PORTAL,
                'company_name' => 'Szkoła Demo Sp. z o.o.',
                'nip' => '5212345678',
                'street' => 'ul. Testowa',
                'house_number' => '19',
                'postal_code' => '00-001',
                'city' => 'Warszawa',
                'invoice_email' => 'fv.demo19@example.test',
                'amount' => 2600,
                'payment_reference' => 'DEMO-19-FV',
                'notes' => self::MARKER.' wniosek z portalu (firma)',
                'status' => ClientInvoiceRequest::STATUS_PENDING,
                'event_code_entered' => $event->code,
            ],
            [
                'key' => 'web-person',
                'event_id' => $event->id,
                'contract_id' => null,
                'user_id' => null,
                'buyer_type' => ClientInvoiceRequest::BUYER_PERSON,
                'source' => ClientInvoiceRequest::SOURCE_WEB,
                'company_name' => 'Anna Kowalska',
                'nip' => null,
                'street' => 'ul. Lipowa',
                'house_number' => '7/2',
                'postal_code' => '30-001',
                'city' => 'Kraków',
                'invoice_email' => 'anna.kowalska.demo@example.test',
                'applicant_phone' => '+48 500 100 200',
                'amount' => 890,
                'payment_reference' => 'DEMO-OSOBA',
                'notes' => self::MARKER.' wniosek WWW (osoba fizyczna)',
                'status' => ClientInvoiceRequest::STATUS_PENDING,
                'event_code_entered' => $event->code,
            ],
            [
                'key' => 'web-company-school',
                'event_id' => $event->id,
                'contract_id' => null,
                'user_id' => null,
                'buyer_type' => ClientInvoiceRequest::BUYER_COMPANY,
                'source' => ClientInvoiceRequest::SOURCE_WEB,
                'company_name' => 'Zespół Szkół Demo nr 3',
                'nip' => '9451234567',
                'street' => 'ul. Szkolna',
                'house_number' => '12',
                'postal_code' => '31-100',
                'city' => 'Kraków',
                'invoice_email' => 'ksiegowosc.zs3.demo@example.test',
                'applicant_phone' => '+48 12 345 67 89',
                'amount' => 14500,
                'payment_reference' => 'DEMO-ZS3',
                'notes' => self::MARKER.' wniosek WWW (szkoła)',
                'status' => ClientInvoiceRequest::STATUS_PENDING,
                'event_code_entered' => $event->code,
            ],
            [
                'key' => 'web-unlinked',
                'event_id' => null,
                'contract_id' => null,
                'user_id' => null,
                'buyer_type' => ClientInvoiceRequest::BUYER_COMPANY,
                'source' => ClientInvoiceRequest::SOURCE_WEB,
                'company_name' => 'Firma Do Powiązania Demo SA',
                'nip' => '7010000000',
                'street' => 'al. Jerozolimskie',
                'house_number' => '100',
                'postal_code' => '00-001',
                'city' => 'Warszawa',
                'invoice_email' => 'fv.unlinked.demo@example.test',
                'amount' => 3200,
                'payment_reference' => 'DEMO-UNLINKED',
                'notes' => self::MARKER.' wniosek WWW bez powiązania — do powiązania w inboxie',
                'status' => ClientInvoiceRequest::STATUS_PENDING,
                'event_code_entered' => '26XXXXXX',
            ],
            [
                'key' => 'processed',
                'event_id' => $event->id,
                'contract_id' => $contract?->id,
                'user_id' => $userId,
                'buyer_type' => ClientInvoiceRequest::BUYER_COMPANY,
                'source' => ClientInvoiceRequest::SOURCE_PORTAL,
                'company_name' => 'Instytucja Zrealizowana Demo',
                'nip' => '1130000000',
                'street' => 'ul. Gotowa',
                'house_number' => '1',
                'postal_code' => '00-002',
                'city' => 'Warszawa',
                'invoice_email' => 'fv.processed.demo@example.test',
                'amount' => 1100,
                'payment_reference' => 'DEMO-DONE',
                'notes' => self::MARKER.' wniosek już zrealizowany',
                'status' => ClientInvoiceRequest::STATUS_PROCESSED,
                'event_code_entered' => $event->code,
                'processed_at' => now()->subDay(),
                'admin_notes' => 'Demo — FV wystawiona',
            ],
        ];

        foreach ($variants as $variant) {
            $key = $variant['key'];
            unset($variant['key']);

            $existing = ClientInvoiceRequest::query()
                ->where('notes', 'like', '%'.self::MARKER.'%')
                ->where('notes', 'like', '%'.$key.'%')
                ->first();

            // Fallback: match by email+notes marker for older single-seed
            if (! $existing && $key === 'portal-company') {
                $existing = ClientInvoiceRequest::query()
                    ->where('event_id', $event->id)
                    ->where('notes', 'like', '%'.self::MARKER.'%')
                    ->where('invoice_email', 'fv.demo19@example.test')
                    ->first();
            }

            if ($existing) {
                $ids[] = '#'.$existing->id.' ('.$key.', już było)';

                continue;
            }

            // Ensure notes contain key for idempotency
            if (! str_contains((string) $variant['notes'], $key)) {
                $variant['notes'] = ($variant['notes'] ?? '').' ['.$key.']';
            }

            $req = ClientInvoiceRequest::query()->create($variant);
            $ids[] = '#'.$req->id.' ('.$key.')';
        }

        return implode(', ', $ids);
    }

    private function seedOnlinePaymentSession(Event $event, ?Contract $contract): string
    {
        if (! $contract || ! Schema::hasTable('online_payment_sessions')) {
            return 'pominięto';
        }

        $schedule = $contract->paymentSchedules()
            ->where(function ($query): void {
                $query->where('paid_amount', '<=', 0)->orWhereNull('paid_amount');
            })
            ->orderBy('sort_order')
            ->first()
            ?? $contract->paymentSchedules()->orderByDesc('sort_order')->first();

        if (! $schedule) {
            return 'brak raty';
        }

        $existing = OnlinePaymentSession::query()
            ->where('event_id', $event->id)
            ->where('description', 'like', '%'.self::MARKER.'%')
            ->first();

        if ($existing) {
            return '#'.$existing->id.' (już było)';
        }

        $session = OnlinePaymentSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'driver' => 'fake',
            'status' => OnlinePaymentSession::STATUS_PENDING,
            'payable_type' => ContractPaymentSchedule::class,
            'payable_id' => $schedule->id,
            'event_id' => $event->id,
            'amount' => $schedule->amount,
            'currency' => 'PLN',
            'description' => self::MARKER.' sesja płatności online — '.$schedule->label,
            'payer_email' => $contract->customer_email,
            'expires_at' => now()->addDays(3),
            'meta' => [
                'contract_id' => $contract->id,
                'schedule_id' => $schedule->id,
                'demo' => true,
            ],
        ]);

        return '#'.$session->id.' → schedule #'.$schedule->id;
    }
}
