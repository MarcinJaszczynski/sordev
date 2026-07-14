<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sticky_notes')) {
            return;
        }

        Schema::create('sticky_notes', function (Blueprint $table) {
            $table->id();
            $table->morphs('notable');
            $table->text('body');
            $table->string('category', 32)->default('general');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['notable_type', 'notable_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sticky_notes');
    }
};
