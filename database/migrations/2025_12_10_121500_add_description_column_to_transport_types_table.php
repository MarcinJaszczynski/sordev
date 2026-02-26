<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('transport_types', 'description')) {
            Schema::table('transport_types', function (Blueprint $table) {
                $table->text('description')->nullable()->after('name');
            });

            if (Schema::hasColumn('transport_types', 'desc')) {
                DB::table('transport_types')->update([
                    'description' => DB::raw('desc'),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transport_types', 'description')) {
            Schema::table('transport_types', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
