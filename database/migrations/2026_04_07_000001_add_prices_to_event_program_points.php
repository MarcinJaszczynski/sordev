<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_program_points', function (Blueprint $table) {
            $table->decimal('calculated_price', 12, 2)->nullable()->after('total_price');
            $table->decimal('planned_price', 12, 2)->nullable()->after('calculated_price');
            $table->decimal('paid_price', 12, 2)->nullable()->after('planned_price');
        });
    }

    public function down(): void
    {
        Schema::table('event_program_points', function (Blueprint $table) {
            $table->dropColumn(['calculated_price', 'planned_price', 'paid_price']);
        });
    }
};
