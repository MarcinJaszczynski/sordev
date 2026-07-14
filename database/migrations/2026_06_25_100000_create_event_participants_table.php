<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_participants')) {
            return;
        }

        Schema::create('event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('pesel', 11)->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('booking_reference')->nullable();
            $table->string('source')->default('manual');
            $table->string('status')->default('active');
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('event_agreement_id')->nullable();
            $table->unsignedBigInteger('participant_payment_id')->nullable();
            $table->string('import_batch_key')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'source']);
        });

        Schema::table('event_participants', function (Blueprint $table) {
            if (Schema::hasTable('contracts')) {
                $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
            }

            if (Schema::hasTable('event_agreements')) {
                $table->foreign('event_agreement_id')->references('id')->on('event_agreements')->nullOnDelete();
            }

            if (Schema::hasTable('event_settlement_participant_payments')) {
                $table->foreign('participant_payment_id')
                    ->references('id')
                    ->on('event_settlement_participant_payments')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_participants');
    }
};
