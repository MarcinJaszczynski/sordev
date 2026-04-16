<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_settlement_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('event_settlements')->cascadeOnDelete();

            // Źródło kosztu (punkt programu lub ręczny wpis)
            $table->string('source_type')->default('manual')
                ->comment('program_point|manual');
            $table->unsignedBigInteger('source_id')->nullable()
                ->comment('ID punktu programu jeśli source_type=program_point');
            $table->string('name')->comment('Nazwa kosztu/pozycji');

            // Koszt planowany
            $table->decimal('planned_amount', 12, 2)->default(0);
            $table->foreignId('planned_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('planned_rate', 10, 4)->nullable()
                ->comment('Kurs do PLN dla planowanego kosztu');
            $table->decimal('planned_amount_pln', 12, 2)->nullable()
                ->comment('Przeliczony koszt planowany w PLN');

            // Koszt rzeczywisty (po dokonaniu płatności)
            $table->decimal('actual_amount', 12, 2)->nullable();
            $table->foreignId('actual_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('rate_snapshot_id')->nullable()
                ->constrained('currency_rate_snapshots')->nullOnDelete()
                ->comment('Kurs użyty przy płatności');
            $table->decimal('actual_rate', 10, 4)->nullable()
                ->comment('Kurs do PLN dla rzeczywistego kosztu');
            $table->decimal('actual_amount_pln', 12, 2)->nullable()
                ->comment('Przeliczony koszt rzeczywisty w PLN');

            // Płatność
            $table->string('paid_by')->default('office')
                ->comment('office|pilot – kto dokonuje płatności');
            $table->string('advance_type')->default('full')
                ->comment('advance|deposit|final|full – typ płatności');
            $table->string('payment_method')->nullable()
                ->comment('cash|transfer|card|other');
            $table->string('document_number')->nullable()
                ->comment('Numer faktury / paragonu / potwierdzenia');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Kto faktycznie zapłacił (użytkownik)');

            $table->string('payment_status')->default('planned')
                ->comment('planned|advance_paid|paid|cancelled');
            $table->timestamp('advance_due_date')->nullable()
                ->comment('Termin zaliczki/rezerwacji');
            $table->decimal('advance_amount', 12, 2)->nullable()
                ->comment('Kwota zaliczki (jeśli advance_type=advance)');

            $table->text('notes')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['settlement_id', 'paid_by']);
            $table->index(['settlement_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_settlement_costs');
    }
};
