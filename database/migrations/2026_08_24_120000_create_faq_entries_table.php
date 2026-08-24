<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_entries', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->text('answer');
            $table->string('category', 50)->default('ogolne');
            $table->string('scope', 30)->default('global');
            $table->foreignId('event_template_id')->nullable()->constrained('event_templates')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->boolean('include_in_schema')->default(true);
            $table->timestamps();

            $table->index(['scope', 'is_published', 'sort_order']);
            $table->index(['event_template_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_entries');
    }
};
