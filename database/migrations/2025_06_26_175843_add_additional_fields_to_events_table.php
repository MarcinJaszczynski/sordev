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
            $table->integer('duration_days')->default(1)->after('end_date');
            $table->integer('transfer_km')->default(0)->after('duration_days');
            $table->integer('program_km')->default(0)->after('transfer_km');
            $table->foreignId('bus_id')->nullable()->constrained('buses')->after('program_km');
            $table->foreignId('markup_id')->nullable()->constrained('markups')->after('bus_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['bus_id']);
            $table->dropForeign(['markup_id']);
            $table->dropColumn(['duration_days', 'transfer_km', 'program_km', 'bus_id', 'markup_id']);
        });
    }
};
