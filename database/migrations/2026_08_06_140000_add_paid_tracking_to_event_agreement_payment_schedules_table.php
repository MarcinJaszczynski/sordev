<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_agreement_payment_schedules')) {
            return;
        }

        Schema::table('event_agreement_payment_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('event_agreement_payment_schedules', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0)->after('amount');
            }

            if (! Schema::hasColumn('event_agreement_payment_schedules', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('paid_amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_agreement_payment_schedules')) {
            return;
        }

        Schema::table('event_agreement_payment_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('event_agreement_payment_schedules', 'paid_at')) {
                $table->dropColumn('paid_at');
            }

            if (Schema::hasColumn('event_agreement_payment_schedules', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }
        });
    }
};
