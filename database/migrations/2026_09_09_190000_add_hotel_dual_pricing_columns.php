<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rozdzielenie ceny hotelu: oferta (S / szablon) vs uzgodniona (P / hotel).
 *
 * - offer_* = zamrożona cena ofertowa
 * - unit_price / flat_amount = cena uzgodniona (negocjowana)
 * - hotel_calculation_source = które źródło idzie do kalkulacji oferty dla klienta
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('events') && ! Schema::hasColumn('events', 'hotel_calculation_source')) {
            Schema::table('events', function (Blueprint $table) {
                $table->string('hotel_calculation_source', 16)
                    ->default('offer')
                    ->after('hotel_flat_stay_convert_to_pln')
                    ->comment('offer|negotiated — źródło ceny hotelu w kalkulacji oferty');
                $table->decimal('hotel_offer_flat_stay_amount', 12, 2)
                    ->nullable()
                    ->after('hotel_calculation_source');
            });
        }

        if (Schema::hasTable('event_hotel_stays') && ! Schema::hasColumn('event_hotel_stays', 'offer_flat_amount')) {
            Schema::table('event_hotel_stays', function (Blueprint $table) {
                $table->decimal('offer_flat_amount', 12, 2)
                    ->nullable()
                    ->after('flat_amount');
            });
        }

        if (Schema::hasTable('event_hotel_room_lines') && ! Schema::hasColumn('event_hotel_room_lines', 'offer_unit_price')) {
            Schema::table('event_hotel_room_lines', function (Blueprint $table) {
                $table->decimal('offer_unit_price', 12, 2)
                    ->default(0)
                    ->after('unit_price');
            });

            // Backfill: istniejące imprezy — oferta = aktualna cena (jedyna warstwa).
            DB::table('event_hotel_room_lines')->update([
                'offer_unit_price' => DB::raw('unit_price'),
            ]);
        }

        if (Schema::hasTable('event_hotel_stays') && Schema::hasColumn('event_hotel_stays', 'offer_flat_amount')) {
            DB::table('event_hotel_stays')
                ->whereNotNull('flat_amount')
                ->whereNull('offer_flat_amount')
                ->update([
                    'offer_flat_amount' => DB::raw('flat_amount'),
                ]);
        }

        if (Schema::hasTable('events') && Schema::hasColumn('events', 'hotel_offer_flat_stay_amount')) {
            DB::table('events')
                ->whereNotNull('hotel_flat_stay_amount')
                ->whereNull('hotel_offer_flat_stay_amount')
                ->update([
                    'hotel_offer_flat_stay_amount' => DB::raw('hotel_flat_stay_amount'),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_hotel_room_lines') && Schema::hasColumn('event_hotel_room_lines', 'offer_unit_price')) {
            Schema::table('event_hotel_room_lines', function (Blueprint $table) {
                $table->dropColumn('offer_unit_price');
            });
        }

        if (Schema::hasTable('event_hotel_stays') && Schema::hasColumn('event_hotel_stays', 'offer_flat_amount')) {
            Schema::table('event_hotel_stays', function (Blueprint $table) {
                $table->dropColumn('offer_flat_amount');
            });
        }

        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (Schema::hasColumn('events', 'hotel_offer_flat_stay_amount')) {
                    $table->dropColumn('hotel_offer_flat_stay_amount');
                }
                if (Schema::hasColumn('events', 'hotel_calculation_source')) {
                    $table->dropColumn('hotel_calculation_source');
                }
            });
        }
    }
};
