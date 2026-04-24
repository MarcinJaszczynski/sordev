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
        Schema::table('event_templates', function (Blueprint $table) {
            $table->string('subtitle')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('event_templates')) {
            Schema::table('event_templates', function (Blueprint $table) {
                try {
                    $table->dropColumn('subtitle');
                } catch (\Throwable $e) {}
            });
        }
    }
};
