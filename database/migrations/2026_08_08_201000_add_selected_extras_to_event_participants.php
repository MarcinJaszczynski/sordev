<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_participants')) {
            return;
        }

        if (! Schema::hasColumn('event_participants', 'selected_extras')) {
            Schema::table('event_participants', function (Blueprint $table) {
                $table->json('selected_extras')->nullable()->after('diet');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_participants') && Schema::hasColumn('event_participants', 'selected_extras')) {
            Schema::table('event_participants', function (Blueprint $table) {
                $table->dropColumn('selected_extras');
            });
        }
    }
};
