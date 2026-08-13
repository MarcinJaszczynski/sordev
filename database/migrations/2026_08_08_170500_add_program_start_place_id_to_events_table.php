<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events') || Schema::hasColumn('events', 'program_start_place_id')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('program_start_place_id')
                ->nullable()
                ->after('start_place_id')
                ->constrained('places')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasColumn('events', 'program_start_place_id')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_start_place_id');
        });
    }
};
