<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_program_points', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_program_points', 'is_hotel_service')) {
                $table->boolean('is_hotel_service')->default(false)->after('is_transport');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_program_points', function (Blueprint $table): void {
            if (Schema::hasColumn('event_program_points', 'is_hotel_service')) {
                $table->dropColumn('is_hotel_service');
            }
        });
    }
};
