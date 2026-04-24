<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_settlement_documents')) {
            return;
        }

        if (! Schema::hasColumn('event_settlement_documents', 'attach_to_pilot_pdf')) {
            Schema::table('event_settlement_documents', function (Blueprint $table) {
                $table->boolean('attach_to_pilot_pdf')->default(false)->after('notes');
            });
        }

        if (! Schema::hasColumn('event_settlement_documents', 'attach_to_hotel_pdf')) {
            Schema::table('event_settlement_documents', function (Blueprint $table) {
                $table->boolean('attach_to_hotel_pdf')->default(false)->after('attach_to_pilot_pdf');
            });
        }

        if (! Schema::hasColumn('event_settlement_documents', 'attach_to_driver_pdf')) {
            Schema::table('event_settlement_documents', function (Blueprint $table) {
                $table->boolean('attach_to_driver_pdf')->default(false)->after('attach_to_hotel_pdf');
            });
        }

        if (! Schema::hasColumn('event_settlement_documents', 'attach_to_folder_pdf')) {
            Schema::table('event_settlement_documents', function (Blueprint $table) {
                $table->boolean('attach_to_folder_pdf')->default(false)->after('attach_to_driver_pdf');
            });
        }

        try {
            Schema::table('event_settlement_documents', function (Blueprint $table) {
                $table->index(['settlement_id', 'attach_to_pilot_pdf'], 'esd_settle_pilot_idx');
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('event_settlement_documents', function (Blueprint $table) {
                $table->index(['settlement_id', 'attach_to_hotel_pdf'], 'esd_settle_hotel_idx');
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('event_settlement_documents', function (Blueprint $table) {
                $table->index(['settlement_id', 'attach_to_driver_pdf'], 'esd_settle_driver_idx');
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('event_settlement_documents', function (Blueprint $table) {
                $table->index(['settlement_id', 'attach_to_folder_pdf'], 'esd_settle_folder_idx');
            });
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_settlement_documents')) {
            return;
        }

        try {
            Schema::table('event_settlement_documents', function (Blueprint $table) {
                $table->dropIndex('esd_settle_pilot_idx');
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('event_settlement_documents', function (Blueprint $table) {
                $table->dropIndex('esd_settle_hotel_idx');
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('event_settlement_documents', function (Blueprint $table) {
                $table->dropIndex('esd_settle_driver_idx');
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('event_settlement_documents', function (Blueprint $table) {
                $table->dropIndex('esd_settle_folder_idx');
            });
        } catch (\Throwable $e) {
        }

        $columnsToDrop = [];
        if (Schema::hasColumn('event_settlement_documents', 'attach_to_pilot_pdf')) {
            $columnsToDrop[] = 'attach_to_pilot_pdf';
        }
        if (Schema::hasColumn('event_settlement_documents', 'attach_to_hotel_pdf')) {
            $columnsToDrop[] = 'attach_to_hotel_pdf';
        }
        if (Schema::hasColumn('event_settlement_documents', 'attach_to_driver_pdf')) {
            $columnsToDrop[] = 'attach_to_driver_pdf';
        }
        if (Schema::hasColumn('event_settlement_documents', 'attach_to_folder_pdf')) {
            $columnsToDrop[] = 'attach_to_folder_pdf';
        }

        if (! empty($columnsToDrop)) {
            Schema::table('event_settlement_documents', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
