<?php

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

        if (! Schema::hasColumn('events', 'diet_info')) {
            Schema::table('events', function (Blueprint $table) {
                $table->text('diet_info')->nullable()->after('participant_count');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        if (Schema::hasColumn('events', 'diet_info')) {
            Schema::table('events', function (Blueprint $table) {
                try {
                    $table->dropColumn('diet_info');
                } catch (\Throwable $e) {
                    // ignore if column does not exist
                }
            });
        }
    }
};
