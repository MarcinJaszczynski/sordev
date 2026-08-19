<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_day_insurance')) {
            return;
        }

        // Zostaw najstarszy wiersz; usuń duplikaty (event, day, insurance_id).
        $duplicates = DB::table('event_day_insurance')
            ->select('event_id', 'day', 'insurance_id', DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('insurance_id')
            ->groupBy('event_id', 'day', 'insurance_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            DB::table('event_day_insurance')
                ->where('event_id', $row->event_id)
                ->where('day', $row->day)
                ->where('insurance_id', $row->insurance_id)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }

        Schema::table('event_day_insurance', function (Blueprint $table): void {
            $table->unique(['event_id', 'day', 'insurance_id'], 'edi_event_day_insurance_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_day_insurance')) {
            return;
        }

        Schema::table('event_day_insurance', function (Blueprint $table): void {
            $table->dropUnique('edi_event_day_insurance_unique');
        });
    }
};
