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
            if (! Schema::hasColumn('events', 'office_notes')) {
                $table->text('office_notes')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('events', 'pilot_notes')) {
                $table->text('pilot_notes')->nullable()->after('office_notes');
            }

            if (! Schema::hasColumn('events', 'driver_notes')) {
                $table->text('driver_notes')->nullable()->after('pilot_notes');
            }

            if (! Schema::hasColumn('events', 'departure_time')) {
                $table->time('departure_time')->nullable()->after('start_place_id');
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

            if (Schema::hasColumn('events', 'driver_notes')) {
                $drop[] = 'driver_notes';
            }

            if (Schema::hasColumn('events', 'pilot_notes')) {
                $drop[] = 'pilot_notes';
            }

            if (Schema::hasColumn('events', 'office_notes')) {
                $drop[] = 'office_notes';
            }

            if (Schema::hasColumn('events', 'departure_time')) {
                $drop[] = 'departure_time';
            }

            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
