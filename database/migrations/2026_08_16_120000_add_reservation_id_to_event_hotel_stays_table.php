<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_hotel_stays') || Schema::hasColumn('event_hotel_stays', 'reservation_id')) {
            return;
        }

        Schema::table('event_hotel_stays', function (Blueprint $table): void {
            $table->foreignId('reservation_id')
                ->nullable()
                ->after('event_program_point_id')
                ->constrained('reservations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_hotel_stays') || ! Schema::hasColumn('event_hotel_stays', 'reservation_id')) {
            return;
        }

        Schema::table('event_hotel_stays', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reservation_id');
        });
    }
};
