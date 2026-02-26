<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('event_snapshots')) {
            return;
        }

        if (!Schema::hasColumn('event_snapshots', 'template_prices_snapshot')) {
            Schema::table('event_snapshots', function (Blueprint $table) {
                $table->json('template_prices_snapshot')->nullable()->after('currency_rates');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('event_snapshots')) {
            return;
        }

        if (Schema::hasColumn('event_snapshots', 'template_prices_snapshot')) {
            Schema::table('event_snapshots', function (Blueprint $table) {
                $table->dropColumn('template_prices_snapshot');
            });
        }
    }
};
