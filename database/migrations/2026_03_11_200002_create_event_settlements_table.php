<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft')
                ->comment('draft|active|pilot_settled|closed');
            $table->foreignId('pilot_id')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Pilot imprezy');
            $table->decimal('planned_cost_pln', 12, 2)->default(0)
                ->comment('Suma kosztów planowanych w PLN');
            $table->decimal('actual_cost_pln', 12, 2)->default(0)
                ->comment('Suma kosztów rzeczywistych w PLN');
            $table->decimal('participant_due_pln', 12, 2)->default(0)
                ->comment('Suma należności od uczestników w PLN');
            $table->decimal('participant_paid_pln', 12, 2)->default(0)
                ->comment('Suma wpłat od uczestników w PLN');
            $table->timestamp('settled_at')->nullable()
                ->comment('Data zamknięcia rozliczenia');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_settlements');
    }
};
