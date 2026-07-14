<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'pilot_panel_access_sent_at')) {
                    $table->timestamp('pilot_panel_access_sent_at')->nullable()->after('pesel');
                }

                if (! Schema::hasColumn('users', 'pilot_panel_access_sent_by')) {
                    $table->foreignId('pilot_panel_access_sent_by')
                        ->nullable()
                        ->after('pilot_panel_access_sent_at')
                        ->constrained('users')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table): void {
                if (! Schema::hasColumn('events', 'pilot_trip_email_sent_at')) {
                    $table->timestamp('pilot_trip_email_sent_at')
                        ->nullable()
                        ->after('shared_with_pilot_by');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (Schema::hasColumn('users', 'pilot_panel_access_sent_by')) {
                    $table->dropConstrainedForeignId('pilot_panel_access_sent_by');
                }

                if (Schema::hasColumn('users', 'pilot_panel_access_sent_at')) {
                    $table->dropColumn('pilot_panel_access_sent_at');
                }
            });
        }

        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table): void {
                if (Schema::hasColumn('events', 'pilot_trip_email_sent_at')) {
                    $table->dropColumn('pilot_trip_email_sent_at');
                }
            });
        }
    }
};
