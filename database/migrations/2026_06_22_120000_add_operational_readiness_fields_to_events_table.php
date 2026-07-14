<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table): void {
            $afterColumn = Schema::hasColumn('events', 'pilot_funds_paid_by')
                ? 'pilot_funds_paid_by'
                : (Schema::hasColumn('events', 'assigned_to') ? 'assigned_to' : null);

            if (! Schema::hasColumn('events', 'check_in_status')) {
                if ($afterColumn) {
                    $table->string('check_in_status')->default('pending')->after($afterColumn);
                } else {
                    $table->string('check_in_status')->default('pending');
                }
            }

            if (! Schema::hasColumn('events', 'check_in_notes')) {
                $table->text('check_in_notes')->nullable()->after('check_in_status');
            }

            if (! Schema::hasColumn('events', 'driver_pickup_info_sent_at')) {
                $table->timestamp('driver_pickup_info_sent_at')->nullable()->after('check_in_notes');
            }

            if (! Schema::hasColumn('events', 'driver_pickup_info_sent_by')) {
                $table->foreignId('driver_pickup_info_sent_by')
                    ->nullable()
                    ->after('driver_pickup_info_sent_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table): void {
            if (Schema::hasColumn('events', 'driver_pickup_info_sent_by')) {
                $table->dropConstrainedForeignId('driver_pickup_info_sent_by');
            }

            foreach (['driver_pickup_info_sent_at', 'check_in_notes', 'check_in_status'] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
