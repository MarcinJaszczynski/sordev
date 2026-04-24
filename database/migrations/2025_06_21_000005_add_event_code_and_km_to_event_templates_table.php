<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('event_templates', 'transfer_km')) {
            Schema::table('event_templates', function (Blueprint $table) {
                $table->integer('transfer_km')->nullable();
            });
        }

        if (! Schema::hasColumn('event_templates', 'program_km')) {
            Schema::table('event_templates', function (Blueprint $table) {
                $table->integer('program_km')->nullable()->after('transfer_km');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('event_templates', 'program_km')) {
            Schema::table('event_templates', function (Blueprint $table) {
                $table->dropColumn('program_km');
            });
        }

        if (Schema::hasColumn('event_templates', 'transfer_km')) {
            Schema::table('event_templates', function (Blueprint $table) {
                $table->dropColumn('transfer_km');
            });
        }
    }
};

// Usunięty plik migracji - nie używać
