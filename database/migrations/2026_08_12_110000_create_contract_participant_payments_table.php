<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contract_participant_payments')) {
            Schema::create('contract_participant_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
                $table->foreignId('participant_payment_id')
                    ->constrained('event_settlement_participant_payments')
                    ->cascadeOnDelete();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->unique(['contract_id', 'participant_payment_id'], 'contract_participant_payment_unique');
                $table->index(['contract_id', 'is_primary'], 'contract_participant_payment_primary_idx');
            });
        }

        $this->backfillFromLegacyColumns();
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_participant_payments');
    }

    private function backfillFromLegacyColumns(): void
    {
        if (! Schema::hasTable('contracts') || ! Schema::hasTable('contract_participant_payments')) {
            return;
        }

        DB::table('contracts')
            ->whereNotNull('participant_payment_id')
            ->select(['id', 'participant_payment_id', 'meta'])
            ->orderBy('id')
            ->chunkById(200, function ($contracts): void {
                foreach ($contracts as $contract) {
                    $this->insertLink((int) $contract->id, (int) $contract->participant_payment_id, true);

                    $meta = is_string($contract->meta ?? null)
                        ? (json_decode($contract->meta, true) ?: [])
                        : (is_array($contract->meta) ? $contract->meta : []);
                    $linkedIds = collect($meta['linked_participant_payment_ids'] ?? [])
                        ->filter()
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values();

                    foreach ($linkedIds as $paymentId) {
                        if ($paymentId === (int) $contract->participant_payment_id) {
                            continue;
                        }

                        $this->insertLink((int) $contract->id, $paymentId, false);
                    }
                }
            });
    }

    private function insertLink(int $contractId, int $paymentId, bool $isPrimary): void
    {
        $exists = DB::table('contract_participant_payments')
            ->where('contract_id', $contractId)
            ->where('participant_payment_id', $paymentId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('contract_participant_payments')->insert([
            'contract_id' => $contractId,
            'participant_payment_id' => $paymentId,
            'is_primary' => $isPrimary,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
