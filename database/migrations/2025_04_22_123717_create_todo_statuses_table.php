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
        Schema::create('todo_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nazwa statusu
            $table->string('color')->nullable(); // Kolor statusu (np. dla oznaczeń w UI)
            $table->string('bgcolor')->nullable(); // Kolor statusu (np. dla oznaczeń w UI)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('todo_statuses');
    }
};
