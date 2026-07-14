<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_settlement_costs') && ! Schema::hasColumn('event_settlement_costs', 'deleted_at')) {
            Schema::table('event_settlement_costs', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('vendor_invoices') && ! Schema::hasColumn('vendor_invoices', 'deleted_at')) {
            Schema::table('vendor_invoices', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_settlement_costs') && Schema::hasColumn('event_settlement_costs', 'deleted_at')) {
            Schema::table('event_settlement_costs', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('vendor_invoices') && Schema::hasColumn('vendor_invoices', 'deleted_at')) {
            Schema::table('vendor_invoices', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
