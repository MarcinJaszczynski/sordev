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

        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'pilot_portal_show_currency_exchange')) {
                $table->boolean('pilot_portal_show_currency_exchange')->default(true);
            }

            if (! Schema::hasColumn('events', 'pilot_portal_show_bus_collections')) {
                $table->boolean('pilot_portal_show_bus_collections')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'pilot_portal_show_bus_collections')) {
                $table->dropColumn('pilot_portal_show_bus_collections');
            }

            if (Schema::hasColumn('events', 'pilot_portal_show_currency_exchange')) {
                $table->dropColumn('pilot_portal_show_currency_exchange');
            }
        });
    }
};
