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
            if (! Schema::hasColumn('events', 'pilot_portal_show_attendance')) {
                $table->boolean('pilot_portal_show_attendance')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table): void {
            if (Schema::hasColumn('events', 'pilot_portal_show_attendance')) {
                $table->dropColumn('pilot_portal_show_attendance');
            }
        });
    }
};
