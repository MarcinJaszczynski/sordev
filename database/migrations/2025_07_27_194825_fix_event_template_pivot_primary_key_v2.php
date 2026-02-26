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
        // SQLite wymaga odbudowy tabeli aby dodać PRIMARY KEY AUTOINCREMENT
        DB::statement('PRAGMA foreign_keys=OFF;');
        
        // Tworzymy tabelę tymczasową z poprawną strukturą
        Schema::create('event_template_event_template_program_point_temp', function (Blueprint $table) {
            $table->id(); // PRIMARY KEY AUTOINCREMENT
            $table->foreignId('event_template_id')->constrained('event_templates')->onDelete('cascade');
            $table->foreignId('event_template_program_point_id')->constrained('event_template_program_points')->onDelete('cascade');
            $table->integer('day');
            $table->integer('order');
            $table->text('notes')->nullable();
            $table->boolean('include_in_program')->default(true);
            $table->boolean('include_in_calculation')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index(['event_template_id', 'day', 'order']);
        });
        
        // Kopiujemy dane - tylko te które mają wszystkie wymagane pola
        DB::statement("
            INSERT INTO event_template_event_template_program_point_temp 
            (event_template_id, event_template_program_point_id, day, `order`, notes, include_in_program, include_in_calculation, active, created_at, updated_at)
            SELECT 
                event_template_id, 
                event_template_program_point_id, 
                day, 
                `order`, 
                COALESCE(notes, '') as notes,
                COALESCE(include_in_program, 1) as include_in_program,
                COALESCE(include_in_calculation, 1) as include_in_calculation,
                COALESCE(active, 1) as active,
                COALESCE(created_at, datetime('now')) as created_at,
                COALESCE(updated_at, datetime('now')) as updated_at
            FROM event_template_event_template_program_point 
            WHERE event_template_id IS NOT NULL 
            AND event_template_program_point_id IS NOT NULL
            AND day IS NOT NULL
            AND `order` IS NOT NULL
        ");
        
        // Usuwamy starą tabelę
        Schema::dropIfExists('event_template_event_template_program_point');
        
        // Zmieniamy nazwę tabeli tymczasowej
        Schema::rename('event_template_event_template_program_point_temp', 'event_template_event_template_program_point');
        
        DB::statement('PRAGMA foreign_keys=ON;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // W przypadku rollback tworzymy tabelę bez PRIMARY KEY (jak było wcześniej)
        DB::statement('PRAGMA foreign_keys=OFF;');
        
        Schema::create('event_template_event_template_program_point_temp', function (Blueprint $table) {
            $table->integer('id')->nullable();
            $table->integer('event_template_id');
            $table->integer('event_template_program_point_id');
            $table->integer('day');
            $table->integer('order');
            $table->text('notes')->nullable();
            $table->boolean('include_in_program')->default(true);
            $table->boolean('include_in_calculation')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        
        DB::statement("
            INSERT INTO event_template_event_template_program_point_temp 
            SELECT * FROM event_template_event_template_program_point
        ");
        
        Schema::dropIfExists('event_template_event_template_program_point');
        Schema::rename('event_template_event_template_program_point_temp', 'event_template_event_template_program_point');
        
        DB::statement('PRAGMA foreign_keys=ON;');
    }
};
