<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_bus_collections')) {
            return;
        }

        Schema::create('event_bus_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained('event_settlements')->nullOnDelete();
            $table->string('title')->default('Zbiórka w autokarze');
            $table->timestamp('collected_at')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->unsignedInteger('participant_count')->nullable();
            $table->decimal('amount_per_person', 12, 2)->nullable();
            $table->string('status')->default('collected')->comment('collected|handed_to_office|confirmed');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'collected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_bus_collections');
    }
};
