<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wcześniej departure_time oznaczało godzinę podstawienia autokaru.
     * Po wydzieleniu substitution_time przenosimy historyczne wartości.
     */
    public function up(): void
    {
        if (! Schema::hasTable('events')
            || ! Schema::hasColumn('events', 'departure_time')
            || ! Schema::hasColumn('events', 'substitution_time')) {
            return;
        }

        DB::table('events')
            ->whereNull('substitution_time')
            ->whereNotNull('departure_time')
            ->update([
                'substitution_time' => DB::raw('departure_time'),
                'departure_time' => null,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')
            || ! Schema::hasColumn('events', 'departure_time')
            || ! Schema::hasColumn('events', 'substitution_time')) {
            return;
        }

        DB::table('events')
            ->whereNull('departure_time')
            ->whereNotNull('substitution_time')
            ->update([
                'departure_time' => DB::raw('substitution_time'),
                'substitution_time' => null,
            ]);
    }
};
