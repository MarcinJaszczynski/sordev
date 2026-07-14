<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'pilot_advance_paid_amount')) {
                $table->decimal('pilot_advance_paid_amount', 12, 2)->nullable()->after('pilot_advance_planned_by');
            }
            if (! Schema::hasColumn('events', 'pilot_advance_paid_currency_id')) {
                $table->foreignId('pilot_advance_paid_currency_id')->nullable()->after('pilot_advance_paid_amount')->constrained('currencies')->nullOnDelete();
            }
            if (! Schema::hasColumn('events', 'pilot_advance_paid_comment')) {
                $table->text('pilot_advance_paid_comment')->nullable()->after('pilot_advance_paid_currency_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'pilot_advance_paid_currency_id')) {
                $table->dropConstrainedForeignId('pilot_advance_paid_currency_id');
            }
            if (Schema::hasColumn('events', 'pilot_advance_paid_amount')) {
                $table->dropColumn('pilot_advance_paid_amount');
            }
            if (Schema::hasColumn('events', 'pilot_advance_paid_comment')) {
                $table->dropColumn('pilot_advance_paid_comment');
            }
        });
    }
};
