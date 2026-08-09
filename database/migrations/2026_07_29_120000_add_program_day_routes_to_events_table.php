<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events') || Schema::hasColumn('events', 'program_day_routes')) {
            return;
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->json('program_day_routes')->nullable()->after('program_day_start_times');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasColumn('events', 'program_day_routes')) {
            return;
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('program_day_routes');
        });
    }
};
