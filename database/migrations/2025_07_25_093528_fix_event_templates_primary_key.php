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
        // W SQLite nie można bezpośrednio dodać PRIMARY KEY AUTOINCREMENT
        // Musimy przebudować tabelę
        
        // 1. Tworzymy tabelę tymczasową z prawidłową strukturą
        Schema::create('event_templates_temp', function (Blueprint $table) {
            $table->id(); // To automatycznie tworzy PRIMARY KEY AUTOINCREMENT
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('duration_days');
            $table->string('featured_image')->nullable();
            $table->text('event_description')->nullable();
            $table->json('gallery')->nullable();
            $table->text('office_description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->decimal('transfer_km', 8, 2)->nullable();
            $table->decimal('program_km', 8, 2)->nullable();
            $table->string('transfer_km2')->nullable();
            $table->string('program_km2')->nullable();
            $table->unsignedBigInteger('bus_id')->nullable();
            $table->unsignedBigInteger('markup_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('seo_keywords')->nullable();
            $table->string('seo_canonical')->nullable();
            $table->string('seo_og_title')->nullable();
            $table->text('seo_og_description')->nullable();
            $table->string('seo_og_image')->nullable();
            $table->string('seo_twitter_title')->nullable();
            $table->text('seo_twitter_description')->nullable();
            $table->string('seo_twitter_image')->nullable();
            $table->json('seo_schema')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('transport_notes')->nullable();
            $table->unsignedBigInteger('start_place_id')->nullable();
            $table->unsignedBigInteger('end_place_id')->nullable();
        });
        
        // 2. Kopiujemy dane ze starej tabeli (tylko te z prawidłowym ID)
        DB::statement('INSERT INTO event_templates_temp (id, name, slug, duration_days, featured_image, event_description, gallery, office_description, notes, created_at, updated_at, deleted_at, transfer_km, program_km, transfer_km2, program_km2, bus_id, markup_id, is_active, seo_title, seo_description, seo_keywords, seo_canonical, seo_og_title, seo_og_description, seo_og_image, seo_twitter_title, seo_twitter_description, seo_twitter_image, seo_schema, subtitle, transport_notes, start_place_id, end_place_id) SELECT id, name, slug, duration_days, featured_image, event_description, gallery, office_description, notes, created_at, updated_at, deleted_at, transfer_km, program_km, transfer_km2, program_km2, bus_id, markup_id, is_active, seo_title, seo_description, seo_keywords, seo_canonical, seo_og_title, seo_og_description, seo_og_image, seo_twitter_title, seo_twitter_description, seo_twitter_image, seo_schema, subtitle, transport_notes, start_place_id, end_place_id FROM event_templates WHERE id IS NOT NULL');
        
        // 3. Usuwamy starą tabelę
        Schema::dropIfExists('event_templates');
        
        // 4. Zmieniamy nazwę tymczasowej tabeli
        Schema::rename('event_templates_temp', 'event_templates');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback - przywracamy starą strukturę (bez PRIMARY KEY AUTOINCREMENT)
        Schema::create('event_templates_old', function (Blueprint $table) {
            $table->integer('id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->integer('duration_days');
            $table->string('featured_image')->nullable();
            $table->text('event_description')->nullable();
            $table->json('gallery')->nullable();
            $table->text('office_description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->decimal('transfer_km', 8, 2)->nullable();
            $table->decimal('program_km', 8, 2)->nullable();
            $table->string('transfer_km2')->nullable();
            $table->string('program_km2')->nullable();
            $table->unsignedBigInteger('bus_id')->nullable();
            $table->unsignedBigInteger('markup_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('seo_keywords')->nullable();
            $table->string('seo_canonical')->nullable();
            $table->string('seo_og_title')->nullable();
            $table->text('seo_og_description')->nullable();
            $table->string('seo_og_image')->nullable();
            $table->string('seo_twitter_title')->nullable();
            $table->text('seo_twitter_description')->nullable();
            $table->string('seo_twitter_image')->nullable();
            $table->json('seo_schema')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('transport_notes')->nullable();
            $table->unsignedBigInteger('start_place_id')->nullable();
            $table->unsignedBigInteger('end_place_id')->nullable();
        });
        
        DB::statement('INSERT INTO event_templates_old SELECT * FROM event_templates');
        Schema::dropIfExists('event_templates');
        Schema::rename('event_templates_old', 'event_templates');
    }
};
