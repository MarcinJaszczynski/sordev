<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_program_points') || Schema::hasColumn('event_program_points', 'contractor_location_id')) {
            return;
        }

        Schema::table('event_program_points', function (Blueprint $table) {
            $table->foreignId('contractor_location_id')
                ->nullable()
                ->after('contractor_id')
                ->constrained('contractor_locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_program_points') || ! Schema::hasColumn('event_program_points', 'contractor_location_id')) {
            return;
        }

        Schema::table('event_program_points', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contractor_location_id');
        });
    }
};
