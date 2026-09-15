<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_program_points')
            || Schema::hasColumn('event_program_points', 'use_planned_price_in_calculation')) {
            return;
        }

        Schema::table('event_program_points', function (Blueprint $table): void {
            $table->boolean('use_planned_price_in_calculation')
                ->default(false)
                ->after('planned_price');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_program_points')
            || ! Schema::hasColumn('event_program_points', 'use_planned_price_in_calculation')) {
            return;
        }

        Schema::table('event_program_points', function (Blueprint $table): void {
            $table->dropColumn('use_planned_price_in_calculation');
        });
    }
};
