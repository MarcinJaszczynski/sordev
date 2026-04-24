<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderToEventTemplateProgramPointsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_template_program_points', function (Blueprint $table) {
            $table->integer('order')->default(0)->after('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_template_program_points', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
}
