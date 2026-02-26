<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_templates', function (Blueprint $table) {
            $table->integer('transfer_km')->nullable()->after('event_code');
            $table->integer('program_km')->nullable()->after('transfer_km');
        });
    }

    public function down(): void
    {
        Schema::table('event_templates', function (Blueprint $table) {
            $table->dropColumn(['event_code', 'transfer_km', 'program_km']);
        });
    }
};

// Usunięty plik migracji - nie używać
