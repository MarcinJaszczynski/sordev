<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('places', 'is_country')) {
            Schema::table('places', function (Blueprint $table) {
                $table->boolean('is_country')->nullable()->after('starting_place');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('places', 'is_country')) {
            Schema::table('places', function (Blueprint $table) {
                $table->dropColumn('is_country');
            });
        }
    }
};
