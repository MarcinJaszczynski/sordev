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

        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'hotel_notes')) {
                $table->text('hotel_notes')->nullable()->after('office_notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasColumn('events', 'hotel_notes')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('hotel_notes');
        });
    }
};
