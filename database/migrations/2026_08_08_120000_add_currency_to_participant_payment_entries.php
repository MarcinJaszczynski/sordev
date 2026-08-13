<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            return;
        }

        Schema::table('event_settlement_participant_payment_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('event_settlement_participant_payment_entries', 'amount')) {
                $table->decimal('amount', 12, 2)->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('event_settlement_participant_payment_entries', 'currency_id')) {
                $table->foreignId('currency_id')
                    ->nullable()
                    ->after('amount')
                    ->constrained('currencies')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('event_settlement_participant_payment_entries', 'rate')) {
                $table->decimal('rate', 12, 6)->nullable()->after('currency_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            return;
        }

        Schema::table('event_settlement_participant_payment_entries', function (Blueprint $table) {
            if (Schema::hasColumn('event_settlement_participant_payment_entries', 'currency_id')) {
                $table->dropConstrainedForeignId('currency_id');
            }
            foreach (['rate', 'amount'] as $column) {
                if (Schema::hasColumn('event_settlement_participant_payment_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
