<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_program_points', function (Blueprint $table) {
            if (! Schema::hasColumn('event_program_points', 'start_date')) {
                $table->date('start_date')->nullable()->after('end_time');
            }
            if (! Schema::hasColumn('event_program_points', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            if (! Schema::hasColumn('event_program_points', 'hide_times')) {
                $table->boolean('hide_times')->default(false)->after('end_date');
            }
        });

        if (Schema::hasColumn('event_program_points', 'parent_id')) {
            Schema::table('event_program_points', function (Blueprint $table) {
                $table->foreign('parent_id')
                    ->references('id')
                    ->on('event_program_points')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('event_program_points', function (Blueprint $table) {
            if (Schema::hasColumn('event_program_points', 'parent_id')) {
                $table->dropForeign(['parent_id']);
            }
            foreach (['hide_times', 'end_date', 'start_date'] as $column) {
                if (Schema::hasColumn('event_program_points', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
