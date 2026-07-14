<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_documents')) {
            return;
        }

        if (Schema::hasColumn('event_documents', 'is_invoice')) {
            return;
        }

        Schema::table('event_documents', function (Blueprint $table) {
            $table->boolean('is_invoice')
                ->default(false)
                ->after('is_offer')
                ->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_documents')) {
            return;
        }

        if (! Schema::hasColumn('event_documents', 'is_invoice')) {
            return;
        }

        Schema::table('event_documents', function (Blueprint $table) {
            $table->dropColumn('is_invoice');
        });
    }
};
