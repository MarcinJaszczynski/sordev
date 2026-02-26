<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_template_starting_place_availability', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_template_id');
            $table->unsignedBigInteger('start_place_id');
            $table->unsignedBigInteger('end_place_id');
            $table->boolean('available')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
            // Foreign keys można dodać jeśli nie używasz SQLite dev
            // $table->foreign('event_template_id')->references('id')->on('event_templates')->onDelete('cascade');
            // $table->foreign('start_place_id')->references('id')->on('places')->onDelete('cascade');
            // $table->foreign('end_place_id')->references('id')->on('places')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_template_starting_place_availability');
    }
};
