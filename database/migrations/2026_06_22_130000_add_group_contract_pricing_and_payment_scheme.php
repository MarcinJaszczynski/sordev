<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                if (! Schema::hasColumn('contracts', 'unit_price')) {
                    $table->decimal('unit_price', 12, 2)->nullable()->after('participant_count');
                }
                if (! Schema::hasColumn('contracts', 'payment_scheme')) {
                    $table->string('payment_scheme', 30)->default('lump_sum')->after('unit_price');
                }
            });
        }

        if (Schema::hasTable('event_agreements')) {
            Schema::table('event_agreements', function (Blueprint $table) {
                if (! Schema::hasColumn('event_agreements', 'unit_price')) {
                    $table->decimal('unit_price', 12, 2)->nullable()->after('participant_count');
                }
                if (! Schema::hasColumn('event_agreements', 'payment_scheme')) {
                    $table->string('payment_scheme', 30)->default('lump_sum')->after('unit_price');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                if (Schema::hasColumn('contracts', 'payment_scheme')) {
                    $table->dropColumn('payment_scheme');
                }
                if (Schema::hasColumn('contracts', 'unit_price')) {
                    $table->dropColumn('unit_price');
                }
            });
        }

        if (Schema::hasTable('event_agreements')) {
            Schema::table('event_agreements', function (Blueprint $table) {
                if (Schema::hasColumn('event_agreements', 'payment_scheme')) {
                    $table->dropColumn('payment_scheme');
                }
                if (Schema::hasColumn('event_agreements', 'unit_price')) {
                    $table->dropColumn('unit_price');
                }
            });
        }
    }
};
