<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'currency_id')) {
                $table->foreignId('currency_id')
                    ->nullable()
                    ->after('reserved_amount')
                    ->constrained('currencies')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservations') || ! Schema::hasColumn('reservations', 'currency_id')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
        });
    }
};
