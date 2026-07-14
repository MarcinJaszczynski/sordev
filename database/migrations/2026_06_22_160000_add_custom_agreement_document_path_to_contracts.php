<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contracts') && ! Schema::hasColumn('contracts', 'custom_agreement_document_path')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->string('custom_agreement_document_path')->nullable()->after('body_edit_mode');
            });
        }

        if (Schema::hasTable('event_agreements') && ! Schema::hasColumn('event_agreements', 'custom_agreement_document_path')) {
            Schema::table('event_agreements', function (Blueprint $table) {
                $table->string('custom_agreement_document_path')->nullable()->after('body_edit_mode');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'custom_agreement_document_path')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropColumn('custom_agreement_document_path');
            });
        }

        if (Schema::hasTable('event_agreements') && Schema::hasColumn('event_agreements', 'custom_agreement_document_path')) {
            Schema::table('event_agreements', function (Blueprint $table) {
                $table->dropColumn('custom_agreement_document_path');
            });
        }
    }
};
