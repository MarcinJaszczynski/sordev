<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_templates', function (Blueprint $table) {
            $table->integer('transfer_km2')->nullable()->after('id');
            $table->integer('program_km2')->nullable()->after('transfer_km2');
        });
    }

    public function down(): void
    {
        Schema::table('event_templates', function (Blueprint $table) {
            $table->dropColumn(['transfer_km2', 'program_km2']);
        });
    }
};
