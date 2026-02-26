<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('transport_types', 'desc') && !Schema::hasColumn('transport_types', 'description')) {
            Schema::table('transport_types', function (Blueprint $table) {
                $table->renameColumn('desc', 'description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transport_types', 'description') && !Schema::hasColumn('transport_types', 'desc')) {
            Schema::table('transport_types', function (Blueprint $table) {
                $table->renameColumn('description', 'desc');
            });
        }
    }
};
