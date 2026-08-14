<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasColumn('events', 'pilot_portal_show_currency_exchange')) {
            return;
        }

        // Tylko default dla nowych imprez — istniejące wartości zostają bez zmian.
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('pilot_portal_show_currency_exchange')->default(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasColumn('events', 'pilot_portal_show_currency_exchange')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->boolean('pilot_portal_show_currency_exchange')->default(true)->change();
        });
    }
};
