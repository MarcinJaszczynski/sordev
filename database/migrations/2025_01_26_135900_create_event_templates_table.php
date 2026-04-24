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
        Schema::create('event_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nazwa imprezy
            $table->string('slug')->unique(); // Slug
            $table->integer('duration_days'); // Długość imprezy (dni)
            $table->string('featured_image')->nullable(); // Zdjęcie wyróżniające
            $table->text('event_description')->nullable(); // Opis imprezy
            $table->json('gallery')->nullable(); // Galeria zdjęć
            $table->text('office_description')->nullable(); // Opis dla biura
            $table->text('notes')->nullable(); // Uwagi
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_templates');
    }
};
