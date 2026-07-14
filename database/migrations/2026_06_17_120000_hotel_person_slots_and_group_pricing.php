<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'hotel_pricing_mode')) {
            Schema::table('events', function (Blueprint $table) {
                $table->string('hotel_pricing_mode', 24)->default('lines')->after('hotel_notes');
                $table->decimal('hotel_flat_stay_amount', 12, 2)->nullable()->after('hotel_pricing_mode');
                $table->foreignId('hotel_flat_stay_currency_id')->nullable()->after('hotel_flat_stay_amount')->constrained('currencies')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('event_hotel_stays', 'pricing_mode')) {
            Schema::table('event_hotel_stays', function (Blueprint $table) {
                $table->string('pricing_mode', 24)->default('lines')->after('same_as_day');
                $table->decimal('flat_amount', 12, 2)->nullable()->after('pricing_mode');
                $table->foreignId('flat_currency_id')->nullable()->after('flat_amount')->constrained('currencies')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('event_hotel_room_occupants', 'unit_index')) {
            Schema::table('event_hotel_room_occupants', function (Blueprint $table) {
                $table->unsignedInteger('unit_index')->default(1)->after('event_hotel_room_line_id');
                $table->unsignedInteger('bed_index')->default(1)->after('unit_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('event_hotel_room_occupants', 'unit_index')) {
            Schema::table('event_hotel_room_occupants', function (Blueprint $table) {
                $table->dropColumn(['unit_index', 'bed_index']);
            });
        }

        if (Schema::hasColumn('event_hotel_stays', 'pricing_mode')) {
            Schema::table('event_hotel_stays', function (Blueprint $table) {
                $table->dropConstrainedForeignId('flat_currency_id');
                $table->dropColumn(['pricing_mode', 'flat_amount']);
            });
        }

        if (Schema::hasColumn('events', 'hotel_pricing_mode')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropConstrainedForeignId('hotel_flat_stay_currency_id');
                $table->dropColumn(['hotel_pricing_mode', 'hotel_flat_stay_amount']);
            });
        }
    }
};
