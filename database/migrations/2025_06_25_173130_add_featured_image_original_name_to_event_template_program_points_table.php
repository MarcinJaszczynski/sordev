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
        Schema::table('event_template_program_points', function (Blueprint $table) {
            $table->string('featured_image_original_name')->nullable()->after('featured_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_template_program_points', function (Blueprint $table) {
            $table->dropColumn('featured_image_original_name');
        });
    }
};
