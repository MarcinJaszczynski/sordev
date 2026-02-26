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
        Schema::create('event_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('action'); // 'created', 'updated', 'deleted', 'program_changed', 'status_changed'
            $table->string('field')->nullable(); // pole które zostało zmienione
            $table->json('old_value')->nullable(); // stara wartość
            $table->json('new_value')->nullable(); // nowa wartość
            $table->text('description')->nullable(); // opis zmiany
            $table->string('ip_address')->nullable();
            $table->timestamps();
            
            $table->index(['event_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_histories');
    }
};
