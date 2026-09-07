<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_template_program_points')) {
            return;
        }

        Schema::table('event_template_program_points', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_template_program_points', 'is_hotel')) {
                $table->boolean('is_hotel')->default(false)->after('include_driver_in_cost');
            }

            if (! Schema::hasColumn('event_template_program_points', 'is_transport')) {
                $table->boolean('is_transport')->default(false)->after('is_hotel');
            }

            if (! Schema::hasColumn('event_template_program_points', 'is_hotel_service')) {
                $table->boolean('is_hotel_service')->default(false)->after('is_transport');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_template_program_points')) {
            return;
        }

        Schema::table('event_template_program_points', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('event_template_program_points', 'is_hotel') ? 'is_hotel' : null,
                Schema::hasColumn('event_template_program_points', 'is_transport') ? 'is_transport' : null,
                Schema::hasColumn('event_template_program_points', 'is_hotel_service') ? 'is_hotel_service' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
