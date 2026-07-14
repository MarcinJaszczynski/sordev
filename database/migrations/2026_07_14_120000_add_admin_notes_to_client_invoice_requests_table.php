<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_invoice_requests') || Schema::hasColumn('client_invoice_requests', 'admin_notes')) {
            return;
        }

        Schema::table('client_invoice_requests', function (Blueprint $table) {
            $table->text('admin_notes')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_invoice_requests') || ! Schema::hasColumn('client_invoice_requests', 'admin_notes')) {
            return;
        }

        Schema::table('client_invoice_requests', function (Blueprint $table) {
            $table->dropColumn('admin_notes');
        });
    }
};
