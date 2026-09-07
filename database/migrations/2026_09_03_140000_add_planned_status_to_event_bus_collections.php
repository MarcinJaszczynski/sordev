<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_bus_collections')) {
            return;
        }

        Schema::table('event_bus_collections', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_bus_collections', 'planned_amount')) {
                $table->decimal('planned_amount', 12, 2)->nullable()->after('amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_bus_collections')) {
            return;
        }

        Schema::table('event_bus_collections', function (Blueprint $table): void {
            if (Schema::hasColumn('event_bus_collections', 'planned_amount')) {
                $table->dropColumn('planned_amount');
            }
        });
    }
};
