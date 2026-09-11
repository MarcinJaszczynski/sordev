<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Automatyczne migawki przy generowaniu oferty / umowy.
     */
    public function up(): void
    {
        if (! Schema::hasTable('event_snapshots')) {
            return;
        }

        Schema::table('event_snapshots', function (Blueprint $table) {
            $table->enum('type', [
                'original',
                'manual',
                'status_change',
                'offer',
                'contract',
            ])->default('original')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_snapshots')) {
            return;
        }

        Schema::table('event_snapshots', function (Blueprint $table) {
            $table->enum('type', [
                'original',
                'manual',
                'status_change',
            ])->default('original')->change();
        });
    }
};
