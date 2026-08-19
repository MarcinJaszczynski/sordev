<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_program_points') || Schema::hasColumn('event_program_points', 'reservation_id')) {
            return;
        }

        Schema::table('event_program_points', function (Blueprint $table): void {
            $table->foreignId('reservation_id')
                ->nullable()
                ->after('contractor_location_id')
                ->constrained('reservations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_program_points') || ! Schema::hasColumn('event_program_points', 'reservation_id')) {
            return;
        }

        Schema::table('event_program_points', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reservation_id');
        });
    }
};
