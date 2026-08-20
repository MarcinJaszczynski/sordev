<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasColumn('events', 'www_extra_info')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('www_extra_info');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events') || Schema::hasColumn('events', 'www_extra_info')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->text('www_extra_info')->nullable();
        });
    }
};
