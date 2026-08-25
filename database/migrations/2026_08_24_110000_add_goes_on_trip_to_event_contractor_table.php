<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_contractor') || Schema::hasColumn('event_contractor', 'goes_on_trip')) {
            return;
        }

        Schema::table('event_contractor', function (Blueprint $table): void {
            $table->boolean('goes_on_trip')
                ->default(false)
                ->after('notes')
                ->comment('Osoba kontaktowa jedzie na wyjazd — widoczna dla pilota');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_contractor') || ! Schema::hasColumn('event_contractor', 'goes_on_trip')) {
            return;
        }

        Schema::table('event_contractor', function (Blueprint $table): void {
            $table->dropColumn('goes_on_trip');
        });
    }
};
