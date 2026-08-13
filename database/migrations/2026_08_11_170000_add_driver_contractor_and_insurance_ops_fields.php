<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'driver_contractor_id')) {
                $table->foreignId('driver_contractor_id')
                    ->nullable()
                    ->after('transport_contractor_id')
                    ->constrained('contractors')
                    ->nullOnDelete();
            }
        });

        Schema::table('insurances', function (Blueprint $table): void {
            if (! Schema::hasColumn('insurances', 'coverage_type')) {
                $table->string('coverage_type', 16)
                    ->nullable()
                    ->after('name')
                    ->comment('nnw|kl');
            }
        });

        if (Schema::hasTable('event_day_insurance') && ! Schema::hasColumn('event_day_insurance', 'is_done')) {
            Schema::table('event_day_insurance', function (Blueprint $table): void {
                $table->boolean('is_done')->default(false)->after('insurance_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_day_insurance') && Schema::hasColumn('event_day_insurance', 'is_done')) {
            Schema::table('event_day_insurance', function (Blueprint $table): void {
                $table->dropColumn('is_done');
            });
        }

        if (Schema::hasColumn('insurances', 'coverage_type')) {
            Schema::table('insurances', function (Blueprint $table): void {
                $table->dropColumn('coverage_type');
            });
        }

        if (Schema::hasColumn('events', 'driver_contractor_id')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('driver_contractor_id');
            });
        }
    }
};
