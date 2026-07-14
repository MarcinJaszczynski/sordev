<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractors') || Schema::hasColumn('contractors', 'uses_business_locations')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table) {
            $table->boolean('uses_business_locations')->default(false)->after('country');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contractors') || ! Schema::hasColumn('contractors', 'uses_business_locations')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn('uses_business_locations');
        });
    }
};
