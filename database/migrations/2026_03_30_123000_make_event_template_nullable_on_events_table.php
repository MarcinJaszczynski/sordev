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
        if (! Schema::hasTable('events') || ! Schema::hasColumn('events', 'event_template_id')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('event_template_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasColumn('events', 'event_template_id')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('event_template_id')->nullable(false)->change();
        });
    }
};
