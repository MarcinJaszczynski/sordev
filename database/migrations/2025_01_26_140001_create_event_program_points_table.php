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
        Schema::create('event_program_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_template_program_point_id')->constrained()->cascadeOnDelete();
            $table->integer('day')->default(1);
            $table->integer('order')->default(1);
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('total_price', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('include_in_program')->default(true);
            $table->boolean('include_in_calculation')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index(['event_id', 'day', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_program_points');
    }
};
