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

        Schema::table('event_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('event_participants', 'parent_access_token')) {
                $table->string('parent_access_token', 64)->nullable()->unique()->after('consents');
            }

            if (! Schema::hasColumn('event_participants', 'parent_access_token_expires_at')) {
                $table->timestamp('parent_access_token_expires_at')->nullable()->after('parent_access_token');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_participants')) {
            return;
        }

        Schema::table('event_participants', function (Blueprint $table) {
            if (Schema::hasColumn('event_participants', 'parent_access_token_expires_at')) {
                $table->dropColumn('parent_access_token_expires_at');
            }

            if (Schema::hasColumn('event_participants', 'parent_access_token')) {
                $table->dropColumn('parent_access_token');
            }
        });
    }
};
