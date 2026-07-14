<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settlement_costs', function (Blueprint $table) {
            if (! Schema::hasColumn('event_settlement_costs', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('document_number');
            }
            if (! Schema::hasColumn('event_settlement_costs', 'receipt_number')) {
                $table->string('receipt_number')->nullable()->after('invoice_number');
            }
        });

        if (Schema::hasColumn('event_settlement_costs', 'document_number')) {
            DB::table('event_settlement_costs')
                ->whereNull('invoice_number')
                ->whereNotNull('document_number')
                ->where(function ($query): void {
                    $query->where('document_number', 'like', 'FV%')
                        ->orWhere('document_number', 'like', 'F/%')
                        ->orWhere('document_number', 'like', '%/FV%');
                })
                ->update([
                    'invoice_number' => DB::raw('document_number'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('event_settlement_costs', function (Blueprint $table) {
            if (Schema::hasColumn('event_settlement_costs', 'receipt_number')) {
                $table->dropColumn('receipt_number');
            }
            if (Schema::hasColumn('event_settlement_costs', 'invoice_number')) {
                $table->dropColumn('invoice_number');
            }
        });
    }
};
