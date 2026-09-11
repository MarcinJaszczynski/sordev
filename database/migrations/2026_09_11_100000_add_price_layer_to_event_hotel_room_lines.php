<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_hotel_room_lines')) {
            return;
        }

        if (! Schema::hasColumn('event_hotel_room_lines', 'price_layer')) {
            Schema::table('event_hotel_room_lines', function (Blueprint $table) {
                $table->string('price_layer', 16)->default('negotiated')->after('event_hotel_stay_id');
                $table->index(['event_hotel_stay_id', 'price_layer'], 'event_hotel_room_lines_stay_layer_idx');
            });
        }

        $hasOfferPrice = Schema::hasColumn('event_hotel_room_lines', 'offer_unit_price');
        $hasPeopleCount = Schema::hasColumn('event_hotel_room_lines', 'people_count');
        $hasPriceBasis = Schema::hasColumn('event_hotel_room_lines', 'price_basis');

        $stayIds = DB::table('event_hotel_room_lines')
            ->distinct()
            ->pluck('event_hotel_stay_id');

        foreach ($stayIds as $stayId) {
            $stayId = (int) $stayId;
            $stayLines = DB::table('event_hotel_room_lines')
                ->where('event_hotel_stay_id', $stayId)
                ->orderBy('order')
                ->orderBy('id')
                ->get();

            if ($stayLines->contains(fn ($row) => ($row->price_layer ?? null) === 'offer')) {
                continue;
            }

            foreach ($stayLines as $stayLine) {
                DB::table('event_hotel_room_lines')->where('id', $stayLine->id)->update([
                    'price_layer' => 'negotiated',
                ]);

                $offerUnit = $hasOfferPrice
                    ? round((float) ($stayLine->offer_unit_price ?? $stayLine->unit_price), 2)
                    : round((float) $stayLine->unit_price, 2);

                $row = [
                    'event_hotel_stay_id' => $stayId,
                    'price_layer' => 'offer',
                    'hotel_room_id' => $stayLine->hotel_room_id,
                    'label' => $stayLine->label,
                    'role' => $stayLine->role,
                    'quantity' => $stayLine->quantity,
                    'unit_price' => $offerUnit,
                    'currency_id' => $stayLine->currency_id,
                    'convert_to_pln' => (bool) ($stayLine->convert_to_pln ?? true),
                    'order' => (int) ($stayLine->order ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($hasPeopleCount) {
                    $row['people_count'] = $stayLine->people_count;
                }
                if ($hasPriceBasis) {
                    $row['price_basis'] = $stayLine->price_basis ?? 'per_room';
                }
                if ($hasOfferPrice) {
                    $row['offer_unit_price'] = $offerUnit;
                }

                DB::table('event_hotel_room_lines')->insert($row);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_hotel_room_lines') || ! Schema::hasColumn('event_hotel_room_lines', 'price_layer')) {
            return;
        }

        if (Schema::hasColumn('event_hotel_room_lines', 'offer_unit_price')) {
            $offerLines = DB::table('event_hotel_room_lines')
                ->where('price_layer', 'offer')
                ->get();

            foreach ($offerLines as $offer) {
                $query = DB::table('event_hotel_room_lines')
                    ->where('event_hotel_stay_id', $offer->event_hotel_stay_id)
                    ->where('price_layer', 'negotiated')
                    ->where('order', $offer->order)
                    ->where('role', $offer->role);

                if ($offer->hotel_room_id) {
                    $query->where('hotel_room_id', $offer->hotel_room_id);
                } else {
                    $query->whereNull('hotel_room_id')->where('label', $offer->label);
                }

                $match = $query->first();
                if ($match) {
                    DB::table('event_hotel_room_lines')->where('id', $match->id)->update([
                        'offer_unit_price' => $offer->unit_price,
                    ]);
                }
            }
        }

        DB::table('event_hotel_room_lines')->where('price_layer', 'offer')->delete();

        Schema::table('event_hotel_room_lines', function (Blueprint $table) {
            $table->dropIndex('event_hotel_room_lines_stay_layer_idx');
            $table->dropColumn('price_layer');
        });
    }
};
