<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contracts') && ! Schema::hasColumn('contracts', 'body_edit_mode')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->string('body_edit_mode', 20)->default('template')->after('agreement_body');
            });
        }

        if (Schema::hasTable('event_agreements') && ! Schema::hasColumn('event_agreements', 'body_edit_mode')) {
            Schema::table('event_agreements', function (Blueprint $table) {
                $table->string('body_edit_mode', 20)->default('template')->after('agreement_body');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'body_edit_mode')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropColumn('body_edit_mode');
            });
        }

        if (Schema::hasTable('event_agreements') && Schema::hasColumn('event_agreements', 'body_edit_mode')) {
            Schema::table('event_agreements', function (Blueprint $table) {
                $table->dropColumn('body_edit_mode');
            });
        }
    }
};
