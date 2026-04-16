<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settlement_costs', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('pending')->index()->after('order');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete()->after('approval_status');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });

        Schema::table('event_settlement_participant_payments', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('pending')->index()->after('notes');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete()->after('approval_status');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });

        Schema::table('pilot_cash_preparations', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('pending')->index()->after('notes');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete()->after('approval_status');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('event_settlement_costs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['approval_status', 'reviewed_at', 'review_notes']);
        });

        Schema::table('event_settlement_participant_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['approval_status', 'reviewed_at', 'review_notes']);
        });

        Schema::table('pilot_cash_preparations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['approval_status', 'reviewed_at', 'review_notes']);
        });
    }
};
