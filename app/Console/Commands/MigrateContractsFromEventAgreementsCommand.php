<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\ContractVariant;
use App\Models\ContractVariantLocation;
use App\Models\ContractVariantTransport;
use App\Models\TfgDictionaryItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateContractsFromEventAgreementsCommand extends Command
{
    protected $signature = 'contracts:migrate-from-event-agreements
                            {--dry-run : Preview without writing}
                            {--event= : Limit migration to a single event_id}';

    protected $description = 'Migrate legacy event_agreements rows into contracts with UFG structure';

    public function handle(): int
    {
        if (! Schema::hasTable('event_agreements')) {
            $this->warn('Table event_agreements does not exist — nothing to migrate.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('contracts')) {
            $this->error('Table contracts does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $eventId = $this->option('event');
        $eventId = filled($eventId) ? (int) $eventId : null;

        $query = DB::table('event_agreements')->orderBy('id');
        if ($eventId) {
            $query->where('event_id', $eventId);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info($eventId
                ? "No event_agreements rows for event #{$eventId}."
                : 'No event_agreements rows found.');

            return self::SUCCESS;
        }

        $this->info($eventId
            ? "Found {$total} legacy agreements for event #{$eventId}."
            : "Found {$total} legacy agreements to migrate.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $migrated = 0;
        $skipped = 0;

        $query->chunkById(100, function ($rows) use ($dryRun, &$migrated, &$skipped, $bar) {
            foreach ($rows as $row) {
                if (Contract::query()->where('legacy_event_agreement_id', $row->id)->exists()) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                if ($dryRun) {
                    $migrated++;
                    $bar->advance();

                    continue;
                }

                DB::transaction(function () use ($row, &$migrated) {
                    $contract = Contract::withoutEvents(function () use ($row) {
                        return Contract::create([
                            'event_id' => $row->event_id,
                            'contract_template_id' => $row->contract_template_id,
                            'legacy_event_agreement_id' => $row->id,
                            'contract_type' => $row->agreement_type ?? Contract::TYPE_GROUP,
                            'participant_payment_id' => $row->participant_payment_id,
                            'title' => $row->title,
                            'contract_number' => $row->agreement_number,
                            'contract_date' => $row->agreement_date,
                            'subject_code' => TfgDictionaryItem::query()->where('type', 'subject')->where('is_active', true)->value('code') ?? 'IT',
                            'payment_method_code' => TfgDictionaryItem::query()->where('type', 'payment_method')->where('is_active', true)->value('code') ?? 'WPLATAPRZED',
                            'event_name' => $row->event_name,
                            'event_start_date' => $row->event_start_date,
                            'event_end_date' => $row->event_end_date,
                            'customer_name' => $row->customer_name,
                            'customer_email' => $row->customer_email,
                            'customer_phone' => $row->customer_phone,
                            'participant_name' => $row->participant_name,
                            'participant_birth_date' => $row->participant_birth_date,
                            'participant_email' => $row->participant_email,
                            'participant_phone' => $row->participant_phone,
                            'participant_count' => $row->participant_count,
                            'total_price' => $row->amount_due,
                            'amount_paid' => $row->amount_paid,
                            'currency' => $row->currency,
                            'status' => $row->status,
                            'payment_status' => $row->payment_status,
                            'client_payment_method' => $row->payment_method,
                            'signer_name' => $row->signer_name,
                            'signer_email' => $row->signer_email,
                            'signer_phone' => $row->signer_phone,
                            'signed_at' => $row->signed_at,
                            'paid_at' => $row->paid_at,
                            'public_token' => $row->public_token,
                            'public_token_expires_at' => $row->public_token_expires_at,
                            'agreement_body' => $row->agreement_body,
                            'attachments' => json_decode($row->attachments ?? 'null', true),
                            'admin_notes' => $row->admin_notes,
                            'meta' => json_decode($row->meta ?? 'null', true),
                            'created_by' => $row->created_by,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at,
                        ]);
                    });

                    $this->createDefaultVariant($contract, $row);
                    $this->createDefaultPayment($contract, $row);

                    $migrated++;
                });

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Migrated: {$migrated}, skipped (already migrated): {$skipped}");

        if ($dryRun) {
            $this->comment('Dry run — no data written.');
        }

        return self::SUCCESS;
    }

    protected function createDefaultVariant(Contract $contract, object $row): void
    {
        $event = $contract->event;
        $variant = ContractVariant::create([
            'contract_id' => $contract->id,
            'sort_order' => 0,
            'travelers_count' => max(1, (int) ($row->participant_count ?? 1)),
            'starts_at' => $row->event_start_date ?? $event?->start_date,
            'ends_at' => $row->event_end_date ?? $event?->end_date,
        ]);

        $countryCode = 'PL';
        if ($event?->startPlace?->country) {
            $countryCode = strtoupper(substr((string) $event->startPlace->country, 0, 2));
        }

        ContractVariantLocation::create([
            'contract_variant_id' => $variant->id,
            'scope_type' => TfgDictionaryItem::scopeForCountry($countryCode) ?: 'PLISAS',
            'country_code' => $countryCode,
            'locality' => $event?->startPlace?->name,
            'sort_order' => 0,
        ]);

        $transportCode = 'NLOT';

        ContractVariantTransport::create([
            'contract_variant_id' => $variant->id,
            'transport_code' => $transportCode,
            'icao_codes' => null,
            'sort_order' => 0,
        ]);
    }

    protected function createDefaultPayment(Contract $contract, object $row): void
    {
        $amountPaid = (float) ($row->amount_paid ?? 0);
        if ($amountPaid <= 0) {
            return;
        }

        ContractPayment::create([
            'contract_id' => $contract->id,
            'amount' => $amountPaid,
            'currency' => $row->currency ?? 'PLN',
            'paid_at' => $row->paid_at ? date('Y-m-d', strtotime((string) $row->paid_at)) : null,
            'payment_method_code' => $contract->payment_method_code,
            'description' => 'Wpłata zmigrowana z event_agreements',
            'participant_payment_id' => $row->participant_payment_id,
            'sort_order' => 0,
        ]);
    }
}
