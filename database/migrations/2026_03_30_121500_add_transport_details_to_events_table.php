<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'transport_company_name')) {
                $table->string('transport_company_name')->nullable()->after('departure_time');
            }

            if (! Schema::hasColumn('events', 'driver_name')) {
                $table->string('driver_name')->nullable()->after('transport_company_name');
            }

            if (! Schema::hasColumn('events', 'driver_phone')) {
                $table->string('driver_phone', 32)->nullable()->after('driver_name');
            }

            if (! Schema::hasColumn('events', 'vehicle_registration')) {
                $table->string('vehicle_registration', 32)->nullable()->after('driver_phone');
            }

            if (! Schema::hasColumn('events', 'pickup_place_details')) {
                $table->text('pickup_place_details')->nullable()->after('start_place_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $drop = [];

            if (Schema::hasColumn('events', 'pickup_place_details')) {
                $drop[] = 'pickup_place_details';
            }

            if (Schema::hasColumn('events', 'vehicle_registration')) {
                $drop[] = 'vehicle_registration';
            }

            if (Schema::hasColumn('events', 'driver_phone')) {
                $drop[] = 'driver_phone';
            }

            if (Schema::hasColumn('events', 'driver_name')) {
                $drop[] = 'driver_name';
            }

            if (Schema::hasColumn('events', 'transport_company_name')) {
                $drop[] = 'transport_company_name';
            }

            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
