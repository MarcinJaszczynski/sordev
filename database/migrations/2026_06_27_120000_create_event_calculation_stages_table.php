<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_calculation_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // 'preliminary' (wstępna), 'predicted' (przewidywana)
            $table->string('stage', 32);
            // Ręcznie zamrożona cena dla klienta (przychód) na danym etapie.
            $table->decimal('client_price_pln', 12, 2)->nullable();
            // Opcjonalny ręczny koszt podwykonawców na danym etapie.
            $table->decimal('cost_pln', 12, 2)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_calculation_stages');
    }
};
