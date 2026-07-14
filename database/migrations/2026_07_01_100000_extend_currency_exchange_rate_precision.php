<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('currencies')) {
            return;
        }

        Schema::table('currencies', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 5)->default(1)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('currencies')) {
            return;
        }

        Schema::table('currencies', function (Blueprint $table) {
            $table->decimal('exchange_rate', 10, 4)->default(1)->change();
        });
    }
};
