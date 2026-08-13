<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'tfg_defaults')) {
                $table->json('tfg_defaults')->nullable()->after('insurance_paid_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasColumn('events', 'tfg_defaults')) {
            return;
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('tfg_defaults');
        });
    }
};
