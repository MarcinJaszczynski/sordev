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
        if (! Schema::hasTable('event_program_points') || ! Schema::hasColumn('event_program_points', 'event_template_program_point_id')) {
            return;
        }

        Schema::table('event_program_points', function (Blueprint $table) {
            $table->unsignedBigInteger('event_template_program_point_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('event_program_points') || ! Schema::hasColumn('event_program_points', 'event_template_program_point_id')) {
            return;
        }

        Schema::table('event_program_points', function (Blueprint $table) {
            $table->unsignedBigInteger('event_template_program_point_id')->nullable(false)->change();
        });
    }
};