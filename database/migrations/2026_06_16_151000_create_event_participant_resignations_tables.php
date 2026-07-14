<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_participant_resignations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained('event_settlements')->nullOnDelete();
            $table->foreignId('participant_payment_id')->nullable()->constrained('event_settlement_participant_payments')->nullOnDelete();
            $table->unsignedBigInteger('contract_id')->nullable()->index();
            $table->unsignedBigInteger('event_agreement_id')->nullable()->index();
            $table->string('participant_name');
            $table->string('resignation_type')->default('contractual');
            $table->string('status')->default('draft');
            $table->date('resigned_at')->nullable();
            $table->decimal('price_per_person_pln', 12, 2)->nullable();
            $table->decimal('amount_paid_pln', 12, 2)->default(0);
            $table->decimal('amount_due_pln', 12, 2)->default(0);
            $table->decimal('refund_amount_pln', 12, 2)->default(0);
            $table->decimal('retention_amount_pln', 12, 2)->default(0);
            $table->string('insurance_policy_number')->nullable();
            $table->decimal('insurance_refund_pln', 12, 2)->nullable();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });

        Schema::create('event_participant_resignation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resignation_id')->constrained('event_participant_resignations')->cascadeOnDelete();
            $table->unsignedBigInteger('program_point_id')->nullable()->index();
            $table->string('description');
            $table->decimal('refunded_amount_pln', 12, 2)->default(0);
            $table->decimal('retained_amount_pln', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_participant_resignation_lines');
        Schema::dropIfExists('event_participant_resignations');
    }
};
