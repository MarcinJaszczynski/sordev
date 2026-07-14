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
            if (! Schema::hasColumn('event_settlement_participant_payment_entries', 'payer_name')) {
                $table->string('payer_name')->nullable()->after('amount_pln');
            }
            if (! Schema::hasColumn('event_settlement_participant_payment_entries', 'bank_transfer_description')) {
                $table->string('bank_transfer_description')->nullable()->after('payer_name');
            }
            if (! Schema::hasColumn('event_settlement_participant_payment_entries', 'payment_kind')) {
                $table->string('payment_kind', 30)->default('regular')->after('bank_transfer_description')
                    ->comment('office_advance|pilot_on_site|regular');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            return;
        }

        Schema::table('event_settlement_participant_payment_entries', function (Blueprint $table) {
            foreach (['payer_name', 'bank_transfer_description', 'payment_kind'] as $column) {
                if (Schema::hasColumn('event_settlement_participant_payment_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
