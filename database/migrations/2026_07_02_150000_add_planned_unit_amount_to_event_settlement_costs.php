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
            if (! Schema::hasColumn('event_settlement_costs', 'planned_unit_amount')) {
                $table->decimal('planned_unit_amount', 12, 2)
                    ->nullable()
                    ->after('planned_amount')
                    ->comment('Kwota planowana za 1 osobę (pilot/biuro)');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_settlement_costs') || ! Schema::hasColumn('event_settlement_costs', 'planned_unit_amount')) {
            return;
        }

        Schema::table('event_settlement_costs', function (Blueprint $table) {
            $table->dropColumn('planned_unit_amount');
        });
    }
};
