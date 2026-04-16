<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('program_point_id')->nullable()->after('event_id');
            $table->foreign('program_point_id')->references('id')->on('event_program_points')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['program_point_id']);
            $table->dropColumn('program_point_id');
        });
    }
};
