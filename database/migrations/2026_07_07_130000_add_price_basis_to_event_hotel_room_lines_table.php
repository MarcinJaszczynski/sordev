<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_hotel_room_lines')) {
            return;
        }

        Schema::table('event_hotel_room_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('event_hotel_room_lines', 'price_basis')) {
                $table->string('price_basis', 16)->default('per_room')->after('unit_price');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_hotel_room_lines')) {
            return;
        }

        Schema::table('event_hotel_room_lines', function (Blueprint $table) {
            if (Schema::hasColumn('event_hotel_room_lines', 'price_basis')) {
                $table->dropColumn('price_basis');
            }
        });
    }
};
