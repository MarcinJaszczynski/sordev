<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('attach_to_pilot_pdf')->default(false)->index();
            $table->boolean('attach_to_hotel_pdf')->default(false)->index();
            $table->boolean('attach_to_driver_pdf')->default(false)->index();
            $table->boolean('attach_to_folder_pdf')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_documents');
    }
};
