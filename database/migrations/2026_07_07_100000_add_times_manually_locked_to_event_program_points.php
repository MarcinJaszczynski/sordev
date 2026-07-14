<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_program_points')) {
            return;
        }

        Schema::table('event_program_points', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_program_points', 'times_manually_locked')) {
                $table->boolean('times_manually_locked')->default(false)->after('hide_times');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_program_points')) {
            return;
        }

        Schema::table('event_program_points', function (Blueprint $table): void {
            if (Schema::hasColumn('event_program_points', 'times_manually_locked')) {
                $table->dropColumn('times_manually_locked');
            }
        });
    }
};
