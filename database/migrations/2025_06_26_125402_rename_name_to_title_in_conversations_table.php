<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite may not support rename if column doesn't exist; guard the call
        if (Schema::hasTable('conversations') && Schema::hasColumn('conversations', 'name')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->renameColumn('name', 'title');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('conversations') && Schema::hasColumn('conversations', 'title')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->renameColumn('title', 'name');
            });
        }
    }
};
