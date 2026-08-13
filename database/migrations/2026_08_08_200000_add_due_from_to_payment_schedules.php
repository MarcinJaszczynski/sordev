<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_payment_schedules')) {
            Schema::table('contract_payment_schedules', function (Blueprint $table): void {
                if (! Schema::hasColumn('contract_payment_schedules', 'due_from')) {
                    $table->date('due_from')->nullable()->after('due_date');
                }
                if (! Schema::hasColumn('contract_payment_schedules', 'due_to')) {
                    $table->date('due_to')->nullable()->after('due_from');
                }
            });
        }

        if (Schema::hasTable('event_agreement_payment_schedules')) {
            Schema::table('event_agreement_payment_schedules', function (Blueprint $table): void {
                if (! Schema::hasColumn('event_agreement_payment_schedules', 'due_from')) {
                    $table->date('due_from')->nullable()->after('due_date');
                }
                if (! Schema::hasColumn('event_agreement_payment_schedules', 'due_to')) {
                    $table->date('due_to')->nullable()->after('due_from');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contract_payment_schedules')) {
            Schema::table('contract_payment_schedules', function (Blueprint $table): void {
                if (Schema::hasColumn('contract_payment_schedules', 'due_to')) {
                    $table->dropColumn('due_to');
                }
                if (Schema::hasColumn('contract_payment_schedules', 'due_from')) {
                    $table->dropColumn('due_from');
                }
            });
        }

        if (Schema::hasTable('event_agreement_payment_schedules')) {
            Schema::table('event_agreement_payment_schedules', function (Blueprint $table): void {
                if (Schema::hasColumn('event_agreement_payment_schedules', 'due_to')) {
                    $table->dropColumn('due_to');
                }
                if (Schema::hasColumn('event_agreement_payment_schedules', 'due_from')) {
                    $table->dropColumn('due_from');
                }
            });
        }
    }
};
