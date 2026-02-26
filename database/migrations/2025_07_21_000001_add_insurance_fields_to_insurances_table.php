<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('insurances', function (Blueprint $table) {
            $table->boolean('insurance_per_day')->default(false);
            $table->boolean('insurance_per_person')->default(false);
            $table->boolean('insurance_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('insurances', function (Blueprint $table) {
            $table->dropColumn(['insurance_per_day', 'insurance_per_person', 'insurance_enabled']);
        });
    }
};
