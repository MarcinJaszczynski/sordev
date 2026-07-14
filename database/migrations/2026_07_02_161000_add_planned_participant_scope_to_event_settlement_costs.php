<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            return;
        }

        Schema::table('event_settlement_costs', function (Blueprint $table) {
            if (! Schema::hasColumn('event_settlement_costs', 'planned_participant_scope')) {
                $table->string('planned_participant_scope', 20)
                    ->default('all')
                    ->after('planned_unit_amount')
                    ->comment('all|paying — liczba osób do przeliczenia kwoty za osobę');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_settlement_costs') || ! Schema::hasColumn('event_settlement_costs', 'planned_participant_scope')) {
            return;
        }

        Schema::table('event_settlement_costs', function (Blueprint $table) {
            $table->dropColumn('planned_participant_scope');
        });
    }
};
