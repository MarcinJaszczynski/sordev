<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasColumn('events', 'code')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->string('code', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasColumn('events', 'code')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->string('code', 16)->nullable()->change();
        });
    }
};
