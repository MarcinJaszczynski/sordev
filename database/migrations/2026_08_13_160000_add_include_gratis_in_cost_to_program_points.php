<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_program_points') && ! Schema::hasColumn('event_program_points', 'include_gratis_in_cost')) {
            Schema::table('event_program_points', function (Blueprint $table): void {
                $table->boolean('include_gratis_in_cost')
                    ->default(false)
                    ->after('include_in_calculation');
            });
        }

        if (Schema::hasTable('event_template_program_points') && ! Schema::hasColumn('event_template_program_points', 'include_gratis_in_cost')) {
            Schema::table('event_template_program_points', function (Blueprint $table): void {
                $table->boolean('include_gratis_in_cost')
                    ->default(false)
                    ->after('convert_to_pln');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_program_points') && Schema::hasColumn('event_program_points', 'include_gratis_in_cost')) {
            Schema::table('event_program_points', function (Blueprint $table): void {
                $table->dropColumn('include_gratis_in_cost');
            });
        }

        if (Schema::hasTable('event_template_program_points') && Schema::hasColumn('event_template_program_points', 'include_gratis_in_cost')) {
            Schema::table('event_template_program_points', function (Blueprint $table): void {
                $table->dropColumn('include_gratis_in_cost');
            });
        }
    }
};
