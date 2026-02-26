<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('place_distances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_place_id');
            $table->unsignedBigInteger('to_place_id');
            $table->float('distance_km')->nullable();
            $table->string('api_source')->nullable();
            $table->timestamps();

            $table->foreign('from_place_id')->references('id')->on('places')->onDelete('cascade');
            $table->foreign('to_place_id')->references('id')->on('places')->onDelete('cascade');
            $table->unique(['from_place_id', 'to_place_id'], 'place_distance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_distances');
    }
};
