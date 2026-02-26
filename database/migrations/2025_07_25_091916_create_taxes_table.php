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
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nazwa podatku (np. "VAT", "Podatek miejski")
            $table->decimal('percentage', 5, 2); // Procentowa wartość podatku (np. 23.00 dla VAT)
            $table->boolean('apply_to_base')->default(false); // Czy liczyć od sumy bez narzutu
            $table->boolean('apply_to_markup')->default(false); // Czy liczyć od narzutu
            $table->boolean('is_active')->default(true); // Czy podatek jest aktywny
            $table->text('description')->nullable(); // Opis podatku
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxes');
    }
};
