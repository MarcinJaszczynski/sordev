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
        Schema::create('event_template_program_point_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_template_program_point_id');
            $table->unsignedBigInteger('tag_id');
            $table->foreign('event_template_program_point_id', 'etppt_pp_fk')
                ->references('id')
                ->on('event_template_program_points')
                ->onDelete('cascade');
            $table->foreign('tag_id', 'etppt_tag_fk')
                ->references('id')
                ->on('tags')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_template_program_point_tag');
    }
};
