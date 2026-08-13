<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_payment_installment_templates') && Schema::hasTable('events')) {
            Schema::create('event_payment_installment_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('label')->nullable();
                /** percent | fixed_pln | foreign */
                $table->string('share_type', 32)->default('percent');
                $table->decimal('percent', 8, 4)->nullable();
                $table->decimal('amount_pln', 12, 2)->nullable();
                $table->decimal('amount_foreign', 12, 2)->nullable();
                $table->string('currency_code', 8)->nullable();
                /** office | pilot */
                $table->string('paid_by', 16)->default('office');
                /** Dni względem start_date imprezy (ujemne = przed startem). */
                $table->smallInteger('due_offset_days')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['event_id', 'sort_order'], 'event_pay_inst_tpl_event_sort_idx');
            });
        }

        if (Schema::hasTable('contract_payment_schedules')) {
            Schema::table('contract_payment_schedules', function (Blueprint $table) {
                if (! Schema::hasColumn('contract_payment_schedules', 'amount_foreign')) {
                    $table->decimal('amount_foreign', 12, 2)->nullable()->after('amount');
                }
                if (! Schema::hasColumn('contract_payment_schedules', 'currency_code')) {
                    $table->string('currency_code', 8)->nullable()->after('amount_foreign');
                }
                if (! Schema::hasColumn('contract_payment_schedules', 'paid_by')) {
                    $table->string('paid_by', 16)->nullable()->after('currency_code');
                }
            });
        }

        if (Schema::hasTable('event_agreement_payment_schedules')) {
            Schema::table('event_agreement_payment_schedules', function (Blueprint $table) {
                if (! Schema::hasColumn('event_agreement_payment_schedules', 'amount_foreign')) {
                    $table->decimal('amount_foreign', 12, 2)->nullable()->after('amount');
                }
                if (! Schema::hasColumn('event_agreement_payment_schedules', 'currency_code')) {
                    $table->string('currency_code', 8)->nullable()->after('amount_foreign');
                }
                if (! Schema::hasColumn('event_agreement_payment_schedules', 'paid_by')) {
                    $table->string('paid_by', 16)->nullable()->after('currency_code');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contract_payment_schedules')) {
            Schema::table('contract_payment_schedules', function (Blueprint $table) {
                foreach (['paid_by', 'currency_code', 'amount_foreign'] as $column) {
                    if (Schema::hasColumn('contract_payment_schedules', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('event_agreement_payment_schedules')) {
            Schema::table('event_agreement_payment_schedules', function (Blueprint $table) {
                foreach (['paid_by', 'currency_code', 'amount_foreign'] as $column) {
                    if (Schema::hasColumn('event_agreement_payment_schedules', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('event_payment_installment_templates');
    }
};
