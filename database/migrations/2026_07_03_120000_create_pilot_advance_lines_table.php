<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pilot_advance_lines')) {
            return;
        }

        Schema::create('pilot_advance_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('amount', 12, 2);
            $table->string('phase', 16); // planned | paid
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'currency_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pilot_advance_lines');
    }
};
