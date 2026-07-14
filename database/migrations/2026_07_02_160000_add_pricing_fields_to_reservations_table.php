<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'amount_basis')) {
                $table->string('amount_basis', 20)
                    ->default('lump_sum')
                    ->after('currency_id')
                    ->comment('per_person|lump_sum');
            }

            if (! Schema::hasColumn('reservations', 'participant_scope')) {
                $table->string('participant_scope', 20)
                    ->default('all')
                    ->after('amount_basis')
                    ->comment('all|paying');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            foreach (['participant_scope', 'amount_basis'] as $column) {
                if (Schema::hasColumn('reservations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
