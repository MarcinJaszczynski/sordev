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

        if (! Schema::hasColumn('event_participants', 'gender')) {
            Schema::table('event_participants', function (Blueprint $table) {
                $table->string('gender', 16)->nullable()->after('last_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_participants') && Schema::hasColumn('event_participants', 'gender')) {
            Schema::table('event_participants', function (Blueprint $table) {
                $table->dropColumn('gender');
            });
        }
    }
};
