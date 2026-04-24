<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('percent', 5, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->date('discount_start')->nullable();
            $table->date('discount_end')->nullable();
            $table->boolean('is_default')->default(false);
            $table->decimal('min_daily_amount_pln', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('markups');
    }
};
