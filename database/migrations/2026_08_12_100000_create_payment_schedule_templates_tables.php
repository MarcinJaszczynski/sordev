<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_schedule_templates')) {
            Schema::create('payment_schedule_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                /** group | individual | custom — puste JSON = uniwersalny */
                $table->json('applies_to')->nullable();
                $table->uuid('version_group_id')->nullable();
                $table->unsignedSmallInteger('version')->default(1);
                $table->boolean('is_active')->default(true);
                $table->text('version_notes')->nullable();
                $table->timestamps();

                $table->index(['version_group_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('payment_schedule_template_installments')) {
            Schema::create('payment_schedule_template_installments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_schedule_template_id');
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
                /** Okno płatności: od / do (D±N). Puste = użyj due_offset_days. */
                $table->smallInteger('due_offset_from_days')->nullable();
                $table->smallInteger('due_offset_to_days')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['payment_schedule_template_id', 'sort_order'], 'pay_sched_tpl_inst_sort_idx');
            });

            Schema::table('payment_schedule_template_installments', function (Blueprint $table) {
                $table->foreign('payment_schedule_template_id', 'pay_sched_tpl_inst_tpl_fk')
                    ->references('id')
                    ->on('payment_schedule_templates')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('contracts') && ! Schema::hasColumn('contracts', 'payment_schedule_template_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('payment_schedule_template_id')->nullable()->after('contract_template_id');
            });
            Schema::table('contracts', function (Blueprint $table) {
                $table->foreign('payment_schedule_template_id', 'contracts_pay_sched_tpl_fk')
                    ->references('id')
                    ->on('payment_schedule_templates')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('event_payment_installment_templates')) {
            Schema::table('event_payment_installment_templates', function (Blueprint $table) {
                if (! Schema::hasColumn('event_payment_installment_templates', 'due_offset_from_days')) {
                    $table->smallInteger('due_offset_from_days')->nullable()->after('due_offset_days');
                }
                if (! Schema::hasColumn('event_payment_installment_templates', 'due_offset_to_days')) {
                    $table->smallInteger('due_offset_to_days')->nullable()->after('due_offset_from_days');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'payment_schedule_template_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropForeign('contracts_pay_sched_tpl_fk');
                $table->dropColumn('payment_schedule_template_id');
            });
        }

        Schema::dropIfExists('payment_schedule_template_installments');
        Schema::dropIfExists('payment_schedule_templates');

        if (Schema::hasTable('event_payment_installment_templates')) {
            Schema::table('event_payment_installment_templates', function (Blueprint $table) {
                foreach (['due_offset_to_days', 'due_offset_from_days'] as $column) {
                    if (Schema::hasColumn('event_payment_installment_templates', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
