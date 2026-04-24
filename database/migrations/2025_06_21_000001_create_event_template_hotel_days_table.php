<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_template_hotel_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_template_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('day');
            $table->json('hotel_room_ids_qty')->nullable();
            $table->json('hotel_room_ids_gratis')->nullable();
            $table->json('hotel_room_ids_staff')->nullable();
            $table->json('hotel_room_ids_driver')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['event_template_id', 'day'], 'uniq_eventtemplate_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_template_hotel_days');
    }
};
