<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contractors') && ! Schema::hasColumn('contractors', 'legacy_contractor_id')) {
            Schema::table('contractors', function (Blueprint $table): void {
                $table->unsignedBigInteger('legacy_contractor_id')->nullable()->after('id');
                $table->unique('legacy_contractor_id', 'contractors_legacy_contractor_id_unique');
            });
        }

        if (Schema::hasTable('legacy_events') && Schema::hasColumn('legacy_events', 'legacy_purchaser_id')) {
            if (! Schema::hasIndex('legacy_events', 'legacy_events_legacy_purchaser_id_index')) {
                Schema::table('legacy_events', function (Blueprint $table): void {
                    $table->index('legacy_purchaser_id', 'legacy_events_legacy_purchaser_id_index');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('legacy_events')) {
            if (Schema::hasIndex('legacy_events', 'legacy_events_legacy_purchaser_id_index')) {
                Schema::table('legacy_events', function (Blueprint $table): void {
                    $table->dropIndex('legacy_events_legacy_purchaser_id_index');
                });
            }
        }

        if (Schema::hasTable('contractors') && Schema::hasColumn('contractors', 'legacy_contractor_id')) {
            Schema::table('contractors', function (Blueprint $table): void {
                $table->dropUnique('contractors_legacy_contractor_id_unique');
                $table->dropColumn('legacy_contractor_id');
            });
        }
    }
};
