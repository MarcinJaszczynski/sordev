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
        Schema::table('todo_statuses', function (Blueprint $table) {
            $table->softDeletes(); // Dodaje kolumnę `deleted_at`
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('todo_statuses', function (Blueprint $table) {
            $table->dropSoftDeletes(); // Usuwa kolumnę `deleted_at`
        });
    }
};
