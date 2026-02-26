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
        Schema::create('event_template_transport_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_template_id');
            $table->unsignedBigInteger('transport_type_id');
            $table->timestamps();

            $table->foreign('event_template_id')->references('id')->on('event_templates')->onDelete('cascade');
            $table->foreign('transport_type_id')->references('id')->on('transport_types')->onDelete('cascade');
            $table->unique(['event_template_id', 'transport_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_template_transport_type');
    }
};
