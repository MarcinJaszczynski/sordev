<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_program_points')) {
            Schema::table('event_program_points', function (Blueprint $table): void {
                if (! Schema::hasColumn('event_program_points', 'include_pilot_in_cost')) {
                    $table->boolean('include_pilot_in_cost')
                        ->default(false)
                        ->after('include_gratis_in_cost');
                }
                if (! Schema::hasColumn('event_program_points', 'include_driver_in_cost')) {
                    $table->boolean('include_driver_in_cost')
                        ->default(false)
                        ->after('include_pilot_in_cost');
                }
            });
        }

        if (Schema::hasTable('event_template_program_points')) {
            Schema::table('event_template_program_points', function (Blueprint $table): void {
                if (! Schema::hasColumn('event_template_program_points', 'include_pilot_in_cost')) {
                    $table->boolean('include_pilot_in_cost')
                        ->default(false)
                        ->after('include_gratis_in_cost');
                }
                if (! Schema::hasColumn('event_template_program_points', 'include_driver_in_cost')) {
                    $table->boolean('include_driver_in_cost')
                        ->default(false)
                        ->after('include_pilot_in_cost');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_program_points')) {
            Schema::table('event_program_points', function (Blueprint $table): void {
                foreach (['include_pilot_in_cost', 'include_driver_in_cost'] as $column) {
                    if (Schema::hasColumn('event_program_points', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('event_template_program_points')) {
            Schema::table('event_template_program_points', function (Blueprint $table): void {
                foreach (['include_pilot_in_cost', 'include_driver_in_cost'] as $column) {
                    if (Schema::hasColumn('event_template_program_points', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
