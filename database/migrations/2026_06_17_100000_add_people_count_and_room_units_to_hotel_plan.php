<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('event_hotel_room_lines', 'people_count')) {
            Schema::table('event_hotel_room_lines', function (Blueprint $table) {
                $table->unsignedTinyInteger('people_count')->nullable()->after('quantity');
            });
        }

        if (! Schema::hasTable('event_hotel_room_units')) {
            Schema::create('event_hotel_room_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_hotel_room_line_id')->constrained('event_hotel_room_lines')->cascadeOnDelete();
                $table->unsignedInteger('unit_index');
                $table->string('room_number', 32)->nullable();
                $table->timestamps();

                $table->unique(['event_hotel_room_line_id', 'unit_index'], 'ehru_line_unit_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_hotel_room_units');

        Schema::table('event_hotel_room_lines', function (Blueprint $table) {
            $table->dropColumn('people_count');
        });
    }
};
