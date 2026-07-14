<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contract_payment_schedules') && Schema::hasTable('contracts')) {
            Schema::create('contract_payment_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('label')->nullable();
                $table->decimal('amount', 12, 2);
                $table->date('due_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['contract_id', 'sort_order'], 'contract_payment_schedules_contract_sort_idx');
            });
        }

        if (! Schema::hasTable('event_agreement_payment_schedules') && Schema::hasTable('event_agreements')) {
            Schema::create('event_agreement_payment_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_agreement_id')->constrained('event_agreements')->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('label')->nullable();
                $table->decimal('amount', 12, 2);
                $table->date('due_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['event_agreement_id', 'sort_order'], 'ea_payment_schedules_agreement_sort_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_agreement_payment_schedules');
        Schema::dropIfExists('contract_payment_schedules');
    }
};
