<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_trip_inquiries')) {
            return;
        }

        Schema::table('client_trip_inquiries', function (Blueprint $table): void {
            if (! Schema::hasColumn('client_trip_inquiries', 'source')) {
                $table->string('source', 16)->default('client')->after('event_portal_access_id');
                $table->index('source');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_trip_inquiries')) {
            return;
        }

        Schema::table('client_trip_inquiries', function (Blueprint $table): void {
            if (Schema::hasColumn('client_trip_inquiries', 'source')) {
                $table->dropIndex(['source']);
                $table->dropColumn('source');
            }
        });
    }
};
