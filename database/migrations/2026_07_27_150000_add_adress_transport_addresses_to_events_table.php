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
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'adress_transport_start')) {
                $table->text('adress_transport_start')->nullable()->after('pickup_place_details');
            }

            if (! Schema::hasColumn('events', 'adress_transport_end')) {
                $table->text('adress_transport_end')->nullable()->after('adress_transport_start');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'adress_transport_end')) {
                $table->dropColumn('adress_transport_end');
            }

            if (Schema::hasColumn('events', 'adress_transport_start')) {
                $table->dropColumn('adress_transport_start');
            }
        });
    }
};
