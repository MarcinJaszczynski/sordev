<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_settlement_participant_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('event_settlements')->cascadeOnDelete();

            $table->string('participant_name')->comment('Imię i nazwisko uczestnika');
            $table->string('booking_reference')->nullable()->comment('Nr rezerwacji');

            $table->decimal('due_amount_pln', 12, 2)->default(0)
                ->comment('Należna kwota wg kalkulacji (PLN)');
            $table->decimal('paid_amount_pln', 12, 2)->default(0)
                ->comment('Faktycznie wpłacona kwota (PLN)');
            $table->decimal('discount_amount_pln', 12, 2)->default(0)
                ->comment('Udzielona zniżka / różnica do wyjaśnienia');

            $table->string('payment_status')->default('pending')
                ->comment('pending|partial|paid|overpaid|cancelled');
            $table->timestamp('payment_date')->nullable();
            $table->string('payment_method')->nullable()
                ->comment('cash|transfer|card|other');
            $table->string('document_number')->nullable();
            $table->boolean('attended')->default(true)
                ->comment('Czy uczestnik pojechał na imprezę');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('settlement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_settlement_participant_payments');
    }
};
