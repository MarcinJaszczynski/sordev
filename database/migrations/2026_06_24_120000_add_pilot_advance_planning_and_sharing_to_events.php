<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'pilot_advance_planned_amount')) {
                $after = Schema::hasColumn('events', 'pilot_funds_paid_by') ? 'pilot_funds_paid_by' : 'assigned_to';
                $table->decimal('pilot_advance_planned_amount', 12, 2)->nullable()->after($after);
            }

            if (! Schema::hasColumn('events', 'pilot_advance_planned_at')) {
                $table->timestamp('pilot_advance_planned_at')->nullable()->after('pilot_advance_planned_amount');
            }

            if (! Schema::hasColumn('events', 'pilot_advance_planned_by')) {
                $table->foreignId('pilot_advance_planned_by')
                    ->nullable()
                    ->after('pilot_advance_planned_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('events', 'shared_with_pilot')) {
                $table->boolean('shared_with_pilot')->default(false)->after('assigned_to');
            }

            if (! Schema::hasColumn('events', 'shared_with_pilot_at')) {
                $table->timestamp('shared_with_pilot_at')->nullable()->after('shared_with_pilot');
            }

            if (! Schema::hasColumn('events', 'shared_with_pilot_by')) {
                $table->foreignId('shared_with_pilot_by')
                    ->nullable()
                    ->after('shared_with_pilot_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('events', 'shared_with_pilot') && Schema::hasColumn('events', 'assigned_to')) {
            DB::table('events')
                ->whereNotNull('assigned_to')
                ->where('shared_with_pilot', false)
                ->update([
                    'shared_with_pilot' => true,
                    'shared_with_pilot_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (Schema::hasColumn('events', 'pilot_advance_planned_by')) {
                $table->dropConstrainedForeignId('pilot_advance_planned_by');
            }

            if (Schema::hasColumn('events', 'pilot_advance_planned_at')) {
                $table->dropColumn('pilot_advance_planned_at');
            }

            if (Schema::hasColumn('events', 'pilot_advance_planned_amount')) {
                $table->dropColumn('pilot_advance_planned_amount');
            }

            if (Schema::hasColumn('events', 'shared_with_pilot_by')) {
                $table->dropConstrainedForeignId('shared_with_pilot_by');
            }

            if (Schema::hasColumn('events', 'shared_with_pilot_at')) {
                $table->dropColumn('shared_with_pilot_at');
            }

            if (Schema::hasColumn('events', 'shared_with_pilot')) {
                $table->dropColumn('shared_with_pilot');
            }
        });
    }
};
