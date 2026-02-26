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
        Schema::create('event_template_event_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_template_id');
            $table->unsignedBigInteger('event_type_id');
            $table->timestamps();

            $table->foreign('event_template_id')->references('id')->on('event_templates')->onDelete('cascade');
            $table->foreign('event_type_id')->references('id')->on('event_types')->onDelete('cascade');
            $table->unique(['event_template_id', 'event_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_template_event_type');
    }
};
