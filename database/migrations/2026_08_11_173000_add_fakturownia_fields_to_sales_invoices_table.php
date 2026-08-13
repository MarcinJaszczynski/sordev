<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_invoices')) {
            return;
        }

        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_invoices', 'fakturownia_id')) {
                $table->unsignedBigInteger('fakturownia_id')->nullable()->after('ksef_number');
            }
            if (! Schema::hasColumn('sales_invoices', 'fakturownia_url')) {
                $table->string('fakturownia_url')->nullable()->after('fakturownia_id');
            }
            if (! Schema::hasColumn('sales_invoices', 'fakturownia_synced_at')) {
                $table->timestamp('fakturownia_synced_at')->nullable()->after('fakturownia_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales_invoices')) {
            return;
        }

        Schema::table('sales_invoices', function (Blueprint $table): void {
            foreach (['fakturownia_synced_at', 'fakturownia_url', 'fakturownia_id'] as $column) {
                if (Schema::hasColumn('sales_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
