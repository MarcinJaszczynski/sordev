<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('transport_types', 'icon_path')) {
            Schema::table('transport_types', function (Blueprint $table) {
                $table->string('icon_path')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transport_types', 'icon_path')) {
            Schema::table('transport_types', function (Blueprint $table) {
                $table->dropColumn('icon_path');
            });
        }
    }
};
