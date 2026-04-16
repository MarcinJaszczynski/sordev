<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_template_event_template_program_point')) {
            Schema::table('event_template_event_template_program_point', function (Blueprint $table) {
                if (!Schema::hasColumn('event_template_event_template_program_point', 'start_time')) {
                    $table->time('start_time')->nullable()->after('notes');
                }

                if (!Schema::hasColumn('event_template_event_template_program_point', 'end_time')) {
                    $table->time('end_time')->nullable()->after('start_time');
                }
            });
        }

        if (Schema::hasTable('event_program_points')) {
            Schema::table('event_program_points', function (Blueprint $table) {
                if (!Schema::hasColumn('event_program_points', 'start_time')) {
                    $table->time('start_time')->nullable()->after('order');
                }

                if (!Schema::hasColumn('event_program_points', 'end_time')) {
                    $table->time('end_time')->nullable()->after('start_time');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_template_event_template_program_point')) {
            Schema::table('event_template_event_template_program_point', function (Blueprint $table) {
                if (Schema::hasColumn('event_template_event_template_program_point', 'end_time')) {
                    try {
                        $table->dropColumn('end_time');
                    } catch (\Throwable $e) {
                    }
                }

                if (Schema::hasColumn('event_template_event_template_program_point', 'start_time')) {
                    try {
                        $table->dropColumn('start_time');
                    } catch (\Throwable $e) {
                    }
                }
            });
        }

        if (Schema::hasTable('event_program_points')) {
            Schema::table('event_program_points', function (Blueprint $table) {
                if (Schema::hasColumn('event_program_points', 'end_time')) {
                    try {
                        $table->dropColumn('end_time');
                    } catch (\Throwable $e) {
                    }
                }

                if (Schema::hasColumn('event_program_points', 'start_time')) {
                    try {
                        $table->dropColumn('start_time');
                    } catch (\Throwable $e) {
                    }
                }
            });
        }
    }
};
