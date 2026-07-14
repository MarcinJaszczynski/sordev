<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'use_manual_transport_cost')) {
                $table->boolean('use_manual_transport_cost')->default(false)->after('bus_id');
            }

            if (! Schema::hasColumn('events', 'manual_transport_cost')) {
                $table->decimal('manual_transport_cost', 12, 2)->nullable()->after('use_manual_transport_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'manual_transport_cost')) {
                $table->dropColumn('manual_transport_cost');
            }

            if (Schema::hasColumn('events', 'use_manual_transport_cost')) {
                $table->dropColumn('use_manual_transport_cost');
            }
        });
    }
};
