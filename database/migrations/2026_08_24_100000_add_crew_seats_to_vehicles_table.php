<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vehicles') || Schema::hasColumn('vehicles', 'crew_seats')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedTinyInteger('crew_seats')
                ->nullable()
                ->after('capacity')
                ->comment('Miejsca załogi (kierowca, pilot) — poza capacity pasażerską');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vehicles') || ! Schema::hasColumn('vehicles', 'crew_seats')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('crew_seats');
        });
    }
};
