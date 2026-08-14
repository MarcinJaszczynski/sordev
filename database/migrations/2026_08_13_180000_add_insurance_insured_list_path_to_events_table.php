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

        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'insurance_insured_list_path')) {
                $table->string('insurance_insured_list_path')->nullable()->after('insurance_document_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table): void {
            if (Schema::hasColumn('events', 'insurance_insured_list_path')) {
                $table->dropColumn('insurance_insured_list_path');
            }
        });
    }
};
