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

        Schema::table('event_program_points', function (Blueprint $table) {
            if (! Schema::hasColumn('event_program_points', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_program_points')) {
            return;
        }

        Schema::table('event_program_points', function (Blueprint $table) {
            if (Schema::hasColumn('event_program_points', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
