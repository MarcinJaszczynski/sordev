<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_rooms', function (Blueprint $table) {
            $table->integer('capacity')->nullable()->after('name');
            $table->string('standard')->nullable()->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_rooms', function (Blueprint $table) {
            $table->dropColumn(['capacity', 'standard']);
        });
    }
};
