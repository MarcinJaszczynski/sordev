<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservations') && ! Schema::hasColumn('reservations', 'convert_to_pln')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->boolean('convert_to_pln')->default(true)->after('currency_id');
            });
        }

        if (Schema::hasTable('event_settlement_costs')) {
            Schema::table('event_settlement_costs', function (Blueprint $table): void {
                if (! Schema::hasColumn('event_settlement_costs', 'planned_amount_basis')) {
                    $table->string('planned_amount_basis', 20)
                        ->default('per_person')
                        ->after('planned_unit_amount');
                }

                if (! Schema::hasColumn('event_settlement_costs', 'planned_convert_to_pln')) {
                    $table->boolean('planned_convert_to_pln')
                        ->default(true)
                        ->after('planned_currency_id');
                }
            });
        }

        if (Schema::hasTable('events') && ! Schema::hasColumn('events', 'hotel_flat_stay_convert_to_pln')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->boolean('hotel_flat_stay_convert_to_pln')->default(true)->after('hotel_flat_stay_currency_id');
            });
        }

        if (Schema::hasTable('event_hotel_stays') && ! Schema::hasColumn('event_hotel_stays', 'flat_convert_to_pln')) {
            Schema::table('event_hotel_stays', function (Blueprint $table): void {
                $table->boolean('flat_convert_to_pln')->default(true)->after('flat_currency_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reservations') && Schema::hasColumn('reservations', 'convert_to_pln')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->dropColumn('convert_to_pln');
            });
        }

        if (Schema::hasTable('event_settlement_costs')) {
            Schema::table('event_settlement_costs', function (Blueprint $table): void {
                if (Schema::hasColumn('event_settlement_costs', 'planned_amount_basis')) {
                    $table->dropColumn('planned_amount_basis');
                }

                if (Schema::hasColumn('event_settlement_costs', 'planned_convert_to_pln')) {
                    $table->dropColumn('planned_convert_to_pln');
                }
            });
        }
        if (Schema::hasTable('events') && Schema::hasColumn('events', 'hotel_flat_stay_convert_to_pln')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropColumn('hotel_flat_stay_convert_to_pln');
            });
        }

        if (Schema::hasTable('event_hotel_stays') && Schema::hasColumn('event_hotel_stays', 'flat_convert_to_pln')) {
            Schema::table('event_hotel_stays', function (Blueprint $table): void {
                $table->dropColumn('flat_convert_to_pln');
            });
        }
    }
};
