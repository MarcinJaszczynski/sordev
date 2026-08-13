<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_invoices') || Schema::hasTable('vendor_invoice_program_point')) {
            return;
        }

        Schema::create('vendor_invoice_program_point', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_invoice_id')->constrained('vendor_invoices')->cascadeOnDelete();
            $table->foreignId('event_program_point_id')->constrained('event_program_points')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['vendor_invoice_id', 'event_program_point_id'],
                'vendor_invoice_program_point_unique'
            );
            $table->index('event_program_point_id');
        });

        if (Schema::hasColumn('vendor_invoices', 'event_program_point_id')) {
            $rows = DB::table('vendor_invoices')
                ->whereNotNull('event_program_point_id')
                ->select(['id', 'event_program_point_id', 'created_at', 'updated_at'])
                ->get();

            $now = now();
            foreach ($rows as $row) {
                DB::table('vendor_invoice_program_point')->insertOrIgnore([
                    'vendor_invoice_id' => $row->id,
                    'event_program_point_id' => $row->event_program_point_id,
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $row->updated_at ?? $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_invoice_program_point');
    }
};
