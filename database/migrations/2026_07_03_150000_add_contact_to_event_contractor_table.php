<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_contractor')) {
            return;
        }

        Schema::table('event_contractor', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_contractor', 'contact_id')) {
                $table->foreignId('contact_id')
                    ->nullable()
                    ->after('contractor_id')
                    ->constrained()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('event_contractor', 'department_label')) {
                $table->string('department_label', 255)
                    ->nullable()
                    ->after('contact_id');
            }
        });

        try {
            Schema::table('event_contractor', function (Blueprint $table): void {
                $table->dropUnique('event_contractor_unique');
            });
        } catch (\Throwable) {
            // legacy DB may not have the index
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_contractor')) {
            return;
        }

        Schema::table('event_contractor', function (Blueprint $table): void {
            if (Schema::hasColumn('event_contractor', 'contact_id')) {
                $table->dropConstrainedForeignId('contact_id');
            }

            if (Schema::hasColumn('event_contractor', 'department_label')) {
                $table->dropColumn('department_label');
            }
        });
    }
};
