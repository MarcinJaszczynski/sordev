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
        if (!Schema::hasTable('events')) {
            return;
        }

        if (!Schema::hasColumn('events', 'start_place_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->unsignedBigInteger('start_place_id')->nullable()->after('bus_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('events')) {
            return;
        }

        if (Schema::hasColumn('events', 'start_place_id')) {
            Schema::table('events', function (Blueprint $table) {
                // dropForeign may fail on SQLite if not present; guard by suppressing exceptions
                try {
                    $table->dropForeign(['start_place_id']);
                } catch (\Throwable $e) {
                    // ignore
                }
                $table->dropColumn('start_place_id');
            });
        }
    }
};
