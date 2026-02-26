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
        Schema::create('event_template_program_points', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nazwa punktu programu
            $table->text('description')->nullable(); // Opis do programu
            $table->text('office_notes')->nullable(); // Uwagi dla biura
            $table->text('pilot_notes')->nullable(); // Uwagi dla pilota
            $table->integer('duration_hours'); // Czas trwania (godziny)
            $table->integer('duration_minutes'); // Czas trwania (minuty)
            $table->string('featured_image')->nullable(); // Zdjęcie wyróżniające
            $table->json('gallery_images')->nullable(); // Zdjęcia do galerii
            $table->decimal('unit_price', 10, 2); // Cena jednostkowa
            $table->integer('group_size')->default(1); // Wielkość grupy (domyślnie 1)
            $table->foreignId('currency_id')->constrained()->onDelete('cascade'); // Waluta
            $table->foreignId('parent_id')->nullable()->constrained('event_template_program_points')->nullOnDelete(); // ID rodzica dla zagnieżdżania punktów programu
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_template_program_points');
    }
};
