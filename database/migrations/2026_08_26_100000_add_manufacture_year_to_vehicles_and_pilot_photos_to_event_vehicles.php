<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicles') && ! Schema::hasColumn('vehicles', 'manufacture_year')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->unsignedSmallInteger('manufacture_year')
                    ->nullable()
                    ->after('model')
                    ->comment('Rocznik (rok produkcji)');
            });
        }

        if (Schema::hasTable('event_vehicles') && ! Schema::hasColumn('event_vehicles', 'pilot_photos')) {
            Schema::table('event_vehicles', function (Blueprint $table) {
                $table->json('pilot_photos')
                    ->nullable()
                    ->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vehicles') && Schema::hasColumn('vehicles', 'manufacture_year')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn('manufacture_year');
            });
        }

        if (Schema::hasTable('event_vehicles') && Schema::hasColumn('event_vehicles', 'pilot_photos')) {
            Schema::table('event_vehicles', function (Blueprint $table) {
                $table->dropColumn('pilot_photos');
            });
        }
    }
};
