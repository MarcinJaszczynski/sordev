<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // W SQLite nie można bezpośrednio zmienić kolumny na PRIMARY KEY AUTOINCREMENT
        // Musimy przebudować tabelę
        
        // 1. Stwórz tabelę tymczasową z poprawną strukturą
        Schema::create('event_templates_temp', function (Blueprint $table) {
            $table->id(); // To automatycznie tworzy PRIMARY KEY AUTOINCREMENT
            $table->string('name');
            $table->string('slug');
            $table->integer('duration_days')->nullable();
            $table->text('featured_image')->nullable();
            $table->text('event_description')->nullable();
            $table->text('gallery')->nullable();
            $table->text('office_description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->decimal('transfer_km', 8, 2)->nullable();
            $table->decimal('program_km', 8, 2)->nullable();
            $table->text('transfer_km2')->nullable();
            $table->text('program_km2')->nullable();
            $table->decimal('bus_id', 8, 2)->nullable();
            $table->decimal('markup_id', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('seo_keywords')->nullable();
            $table->text('seo_canonical')->nullable();
            $table->text('seo_og_title')->nullable();
            $table->text('seo_og_description')->nullable();
            $table->text('seo_og_image')->nullable();
            $table->text('seo_twitter_title')->nullable();
            $table->text('seo_twitter_description')->nullable();
            $table->text('seo_twitter_image')->nullable();
            $table->text('seo_schema')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('transport_notes')->nullable();
            $table->integer('start_place_id')->nullable();
            $table->integer('end_place_id')->nullable();
        });
        
        // 2. Skopiuj dane z oryginalnej tabeli (z maksymalnym ID + 1 dla auto-increment)
        $maxId = DB::table('event_templates')->max('id') ?? 0;
        
        DB::statement("
            INSERT INTO event_templates_temp (
                id, name, slug, duration_days, featured_image, event_description, 
                gallery, office_description, notes, created_at, updated_at, deleted_at,
                transfer_km, program_km, transfer_km2, program_km2, bus_id, markup_id, 
                is_active, seo_title, seo_description, seo_keywords, seo_canonical,
                seo_og_title, seo_og_description, seo_og_image, seo_twitter_title,
                seo_twitter_description, seo_twitter_image, seo_schema, subtitle,
                transport_notes, start_place_id, end_place_id
            ) 
            SELECT 
                id, name, slug, duration_days, featured_image, event_description, 
                gallery, office_description, notes, created_at, updated_at, deleted_at,
                transfer_km, program_km, transfer_km2, program_km2, bus_id, markup_id, 
                is_active, seo_title, seo_description, seo_keywords, seo_canonical,
                seo_og_title, seo_og_description, seo_og_image, seo_twitter_title,
                seo_twitter_description, seo_twitter_image, seo_schema, subtitle,
                transport_notes, start_place_id, end_place_id
            FROM event_templates 
            WHERE id IS NOT NULL
        ");
        
        // 3. Ustaw AUTOINCREMENT na następną wartość
        DB::statement("UPDATE sqlite_sequence SET seq = ? WHERE name = 'event_templates_temp'", [$maxId]);
        
        // 4. Usuń starą tabelę
        Schema::dropIfExists('event_templates');
        
        // 5. Zmień nazwę tabeli tymczasowej na właściwą
        Schema::rename('event_templates_temp', 'event_templates');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cofanie nie jest możliwe bez utraty danych - zostawiamy jak jest
        // W praktyce nie powinniśmy cofać tej migracji
    }
};
