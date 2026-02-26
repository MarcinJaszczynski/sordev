<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['original', 'manual', 'status_change'])->default('original');
            $table->string('name')->nullable(); // nazwa snapshotu
            $table->text('description')->nullable(); // opis powodu utworzenia snapshotu
            
            // Dane imprezy w momencie snapshotu
            $table->json('event_data'); // podstawowe dane imprezy
            $table->json('program_points'); // punkty programu
            $table->json('calculations'); // kalkulacje i koszty
            $table->json('currency_rates')->nullable(); // kursy walut
            $table->json('template_prices_snapshot')->nullable(); // uproszczony zrzut cen z szablonu dla tej lokalizacji i qty
            $table->decimal('total_cost_snapshot', 10, 2); // koszt całkowity w momencie snapshotu
            
            // Metadane
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('snapshot_date'); // data utworzenia snapshotu
            $table->timestamps();
            
            $table->index(['event_id', 'type']);
            $table->index(['event_id', 'snapshot_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_snapshots');
    }
};
