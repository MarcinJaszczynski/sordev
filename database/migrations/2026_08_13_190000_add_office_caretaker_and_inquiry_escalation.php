<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('events') && ! Schema::hasColumn('events', 'office_caretaker_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->foreignId('office_caretaker_id')
                    ->nullable()
                    ->after('assigned_to')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('client_trip_inquiries') && ! Schema::hasColumn('client_trip_inquiries', 'escalated_at')) {
            Schema::table('client_trip_inquiries', function (Blueprint $table) {
                $table->timestamp('escalated_at')->nullable()->after('answered_at');
                $table->index(['status', 'escalated_at', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('client_trip_inquiries') && Schema::hasColumn('client_trip_inquiries', 'escalated_at')) {
            Schema::table('client_trip_inquiries', function (Blueprint $table) {
                $table->dropIndex(['status', 'escalated_at', 'created_at']);
                $table->dropColumn('escalated_at');
            });
        }

        if (Schema::hasTable('events') && Schema::hasColumn('events', 'office_caretaker_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropConstrainedForeignId('office_caretaker_id');
            });
        }
    }
};
