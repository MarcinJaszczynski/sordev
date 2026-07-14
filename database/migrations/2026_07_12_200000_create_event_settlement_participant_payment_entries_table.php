<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Odtworzenie po nieudanej migracji (MySQL: zbyt długa nazwa FK → tabela bez constraintów).
        if (Schema::hasTable('event_settlement_participant_payment_entries')) {
            Schema::drop('event_settlement_participant_payment_entries');
        }

        Schema::create('event_settlement_participant_payment_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('participant_payment_id');
            $table->dateTime('paid_at');
            $table->decimal('amount_pln', 12, 2);
            $table->string('source', 30)->default('manual')
                ->comment('manual|bank_import|online');
            $table->string('payment_method', 30)->nullable()
                ->comment('transfer|cash|card|other');
            $table->unsignedBigInteger('bank_payment_import_line_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('participant_payment_id', 'espp_entry_payment_fk')
                ->references('id')
                ->on('event_settlement_participant_payments')
                ->cascadeOnDelete();

            if (Schema::hasTable('bank_payment_import_lines')) {
                $table->foreign('bank_payment_import_line_id', 'espp_entry_bank_line_fk')
                    ->references('id')
                    ->on('bank_payment_import_lines')
                    ->nullOnDelete();
            }

            $table->foreign('created_by', 'espp_entry_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('participant_payment_id', 'espp_entry_payment_idx');
            $table->index('paid_at', 'espp_entry_paid_at_idx');
        });

        $this->backfillExistingPayments();
    }

    public function down(): void
    {
        Schema::dropIfExists('event_settlement_participant_payment_entries');
    }

    private function backfillExistingPayments(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payments')) {
            return;
        }

        $payments = DB::table('event_settlement_participant_payments')
            ->where('paid_amount_pln', '>', 0)
            ->get(['id', 'paid_amount_pln', 'payment_date', 'payment_method']);

        foreach ($payments as $payment) {
            $exists = DB::table('event_settlement_participant_payment_entries')
                ->where('participant_payment_id', $payment->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('event_settlement_participant_payment_entries')->insert([
                'participant_payment_id' => $payment->id,
                'paid_at' => $payment->payment_date ?? now(),
                'amount_pln' => $payment->paid_amount_pln,
                'source' => 'manual',
                'payment_method' => $payment->payment_method,
                'notes' => 'Backfill z sumy wpłat',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
