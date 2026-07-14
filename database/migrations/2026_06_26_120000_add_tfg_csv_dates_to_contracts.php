<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->date('tfg_change_date')->nullable()->after('correction_reason');
            $table->date('tfg_termination_date')->nullable()->after('tfg_change_date');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['tfg_change_date', 'tfg_termination_date']);
        });
    }
};
