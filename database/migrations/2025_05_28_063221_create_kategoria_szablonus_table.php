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
        Schema::create('kategoria_szablonus', function (Blueprint $table) {
            $table->id();
            $table->string('nazwa');
            $table->text('opis')->nullable();
            $table->text('uwagi')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('kategoria_szablonus')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kategoria_szablonus');
    }
};
