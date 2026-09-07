<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Usuwa lokalne artefakty testów (factory), które zaśmieciły katalog i imprezy.
 */
class CleanupLocalTestJunkCommand extends Command
{
    protected $signature = 'catalog:cleanup-test-junk
        {--dry-run : Tylko raport, bez zapisu}';

    protected $description = 'Usuwa lokalne szablony/imprezy/busy/qty/ubezpieczenia z testów (nie rusza danych z produ)';

    /** @var list<int> */
    private const TEST_EVENT_IDS = [11, 32, 33, 43, 44, 45, 47, 66, 67, 68, 69, 70];

    /** @var list<int> */
    private const TEST_TEMPLATE_IDS = [730, 731, 732, 733, 734, 735, 736, 737, 738, 739];

    /** @var list<int> */
    private const TEST_BUS_IDS = [13, 14, 15];

    /** @var list<int> */
    private const TEST_QTY_IDS = [42, 43, 44, 45];

    /** @var list<int> */
    private const TEST_INSURANCE_IDS = [7, 8, 9, 10, 11, 12, 13];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? '[DRY-RUN] ' : '').'Sprzątanie lokalnych śmieci testowych…');

        $plan = $this->buildPlan();
        foreach ($plan as $label => $count) {
            $this->line(sprintf('  %-45s %s', $label, number_format($count, 0, ',', ' ')));
        }

        if ($dryRun) {
            $this->info('Dry-run — nic nie usunięto.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function (): void {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                try {
                    $this->deleteTestEvents();
                    $this->deleteTestTemplates();
                    $this->deleteTestQuantities();
                    $this->deleteTestBuses();
                    $this->deleteTestInsurances();
                } finally {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                }
            });
        } catch (Throwable $e) {
            $this->error('Cleanup nieudany: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Sprzątanie zakończone.');
        $this->verify();

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function buildPlan(): array
    {
        return [
            'events (test)' => DB::table('events')->whereIn('id', self::TEST_EVENT_IDS)->count(),
            'event_templates 730-739' => DB::table('event_templates')->whereIn('id', self::TEST_TEMPLATE_IDS)->count(),
            'template prices leftover' => DB::table('event_template_price_per_person')
                ->whereIn('event_template_id', self::TEST_TEMPLATE_IDS)->count(),
            'buses 13-15' => DB::table('buses')->whereIn('id', self::TEST_BUS_IDS)->count(),
            'qties 42-45' => DB::table('event_template_qties')->whereIn('id', self::TEST_QTY_IDS)->count(),
            'insurances 7-13' => DB::table('insurances')->whereIn('id', self::TEST_INSURANCE_IDS)->count(),
            'event_day_insurance (test ins)' => DB::table('event_day_insurance')
                ->whereIn('insurance_id', self::TEST_INSURANCE_IDS)->count(),
        ];
    }

    private function deleteTestEvents(): void
    {
        $eventIds = DB::table('events')->whereIn('id', self::TEST_EVENT_IDS)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($eventIds === []) {
            return;
        }

        $settlementIds = DB::table('event_settlements')->whereIn('event_id', $eventIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($settlementIds !== []) {
            $costIds = DB::table('event_settlement_costs')->whereIn('settlement_id', $settlementIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($costIds !== []) {
                DB::table('event_documents')->whereIn('settlement_cost_id', $costIds)->delete();
                DB::table('vendor_invoices')->whereIn('event_settlement_cost_id', $costIds)->update(['event_settlement_cost_id' => null]);
                DB::table('reservations')->whereIn('settlement_cost_id', $costIds)->update(['settlement_cost_id' => null]);
                DB::table('event_settlement_costs')->whereIn('id', $costIds)->delete();
            }
            DB::table('event_settlement_cost_groups')->whereIn('settlement_id', $settlementIds)->delete();
            DB::table('event_settlement_documents')->whereIn('settlement_id', $settlementIds)->delete();
            DB::table('event_settlement_participant_payments')->whereIn('settlement_id', $settlementIds)->delete();
            DB::table('pilot_cash_preparations')->whereIn('settlement_id', $settlementIds)->delete();
            DB::table('pilot_currency_exchanges')->whereIn('settlement_id', $settlementIds)->delete();
            DB::table('event_bus_collections')->whereIn('settlement_id', $settlementIds)->delete();
            DB::table('event_settlements')->whereIn('id', $settlementIds)->delete();
        }

        $stayIds = DB::table('event_hotel_stays')->whereIn('event_id', $eventIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($stayIds !== []) {
            DB::table('event_hotel_room_lines')->whereIn('event_hotel_stay_id', $stayIds)->delete();
            DB::table('hotel_correspondence_logs')->whereIn('event_hotel_stay_id', $stayIds)->delete();
            DB::table('event_hotel_stays')->whereIn('id', $stayIds)->delete();
        }

        foreach ([
            'event_contractor',
            'event_day_insurance',
            'event_histories',
            'event_insurance_policies',
            'event_price_per_person',
            'event_program_points',
            'event_qties',
            'event_attendances',
            'event_bus_collections',
            'event_calculation_stages',
            'event_message_logs',
            'event_package_documents',
            'event_participant_resignations',
            'event_participants',
            'event_payment_installment_templates',
            'event_portal_accesses',
            'event_vehicles',
            'hotel_correspondence_logs',
            'pilot_advance_lines',
            'pilot_fee_lines',
            'sales_invoices',
            'client_trip_inquiries',
            'reservations',
        ] as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }
            DB::table($table)->whereIn('event_id', $eventIds)->delete();
        }

        foreach (['bank_payment_import_lines', 'client_invoice_requests', 'contracts', 'online_payment_sessions', 'vendor_invoices'] as $table) {
            if ($this->tableExists($table)) {
                DB::table($table)->whereIn('event_id', $eventIds)->update(['event_id' => null]);
            }
        }

        DB::table('events')->whereIn('id', $eventIds)->delete();
        $this->line('  usunięto events: '.count($eventIds));
    }

    private function deleteTestTemplates(): void
    {
        $templateIds = self::TEST_TEMPLATE_IDS;

        foreach ([
            'event_template_price_per_person',
            'event_template_event_template_program_point',
            'event_template_hotel_days',
            'event_template_day_insurance',
            'event_template_starting_place_availability',
            'event_template_tag',
            'event_template_tax',
            'event_template_transport_type',
            'event_template_event_type',
            'event_template_program_point_child_pivot',
            'event_template_event_price_description',
        ] as $table) {
            if ($this->tableExists($table)) {
                DB::table($table)->whereIn('event_template_id', $templateIds)->delete();
            }
        }

        DB::table('faq_entries')->whereIn('event_template_id', $templateIds)->update(['event_template_id' => null]);
        // SoftDeletes: hard delete
        DB::table('event_templates')->whereIn('id', $templateIds)->delete();
        $this->line('  usunięto event_templates 730-739');
    }

    private function deleteTestQuantities(): void
    {
        DB::table('event_template_price_per_person')->whereIn('event_template_qty_id', self::TEST_QTY_IDS)->delete();
        DB::table('event_price_per_person')->whereIn('event_template_qty_id', self::TEST_QTY_IDS)->delete();
        if ($this->tableExists('event_qties')) {
            // event_qties może trzymać qty_id
            $cols = collect(DB::select('DESCRIBE event_qties'))->pluck('Field')->all();
            if (in_array('event_template_qty_id', $cols, true)) {
                DB::table('event_qties')->whereIn('event_template_qty_id', self::TEST_QTY_IDS)->delete();
            }
        }
        DB::table('event_template_qties')->whereIn('id', self::TEST_QTY_IDS)->delete();
        $this->line('  usunięto qties 42-45');
    }

    private function deleteTestBuses(): void
    {
        DB::table('events')->whereIn('bus_id', self::TEST_BUS_IDS)->update(['bus_id' => null]);
        DB::table('event_templates')->whereIn('bus_id', self::TEST_BUS_IDS)->update(['bus_id' => null]);
        DB::table('buses')->whereIn('id', self::TEST_BUS_IDS)->delete();
        $this->line('  usunięto buses 13-15');
    }

    private function deleteTestInsurances(): void
    {
        DB::table('event_day_insurance')->whereIn('insurance_id', self::TEST_INSURANCE_IDS)->delete();
        DB::table('event_template_day_insurance')->whereIn('insurance_id', self::TEST_INSURANCE_IDS)->delete();
        DB::table('insurances')->whereIn('id', self::TEST_INSURANCE_IDS)->delete();
        $this->line('  usunięto insurances 7-13');
    }

    private function verify(): void
    {
        $left = [
            'events' => DB::table('events')->whereIn('id', self::TEST_EVENT_IDS)->count(),
            'templates' => DB::table('event_templates')->whereIn('id', self::TEST_TEMPLATE_IDS)->count(),
            'buses' => DB::table('buses')->whereIn('id', self::TEST_BUS_IDS)->count(),
            'qties' => DB::table('event_template_qties')->whereIn('id', self::TEST_QTY_IDS)->count(),
            'insurances' => DB::table('insurances')->whereIn('id', self::TEST_INSURANCE_IDS)->count(),
        ];
        foreach ($left as $k => $v) {
            $this->line(sprintf('  leftover %-12s %d', $k, $v));
        }
    }

    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }
}
