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
        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nazwa kontrahenta
            $table->string('street')->nullable(); // Ulica
            $table->string('house_number')->nullable(); // Numer domu
            $table->string('city')->nullable(); // Miejscowość
            $table->string('postal_code')->nullable(); // Kod pocztowy
            $table->enum('status', ['active', 'inactive'])->default('active'); // Status (aktywny/nieaktywny)
            $table->text('office_notes')->nullable(); // Uwagi dla biura
            $table->timestamps();
            $table->softDeletes(); // Obsługa Soft Deletes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contractors');
    }
};
