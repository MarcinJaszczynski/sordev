<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settlements', function (Blueprint $table) {
            if (! Schema::hasColumn('event_settlements', 'pilot_report_notes')) {
                $table->text('pilot_report_notes')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('event_settlements', 'reported_participant_count')) {
                $table->unsignedInteger('reported_participant_count')->nullable()->after('pilot_report_notes');
            }
            if (! Schema::hasColumn('event_settlements', 'odometer_start')) {
                $table->unsignedInteger('odometer_start')->nullable()->after('reported_participant_count');
            }
            if (! Schema::hasColumn('event_settlements', 'odometer_end')) {
                $table->unsignedInteger('odometer_end')->nullable()->after('odometer_start');
            }
            if (! Schema::hasColumn('event_settlements', 'pilot_report_updated_at')) {
                $table->timestamp('pilot_report_updated_at')->nullable()->after('odometer_end');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_settlements', function (Blueprint $table) {
            $columns = [
                'pilot_report_notes',
                'reported_participant_count',
                'odometer_start',
                'odometer_end',
                'pilot_report_updated_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('event_settlements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
