<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_package_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('audience', 32);
            $table->string('edit_mode', 32)->default('live');
            $table->string('status', 32)->default('draft');
            $table->json('overrides')->nullable();
            $table->longText('frozen_html')->nullable();
            $table->string('upload_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'audience']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_package_documents');
    }
};
