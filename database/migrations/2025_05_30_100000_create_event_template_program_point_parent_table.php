<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventTemplateProgramPointParentTable extends Migration
{
    public function up(): void
    {
        Schema::create('event_template_program_point_parent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('event_template_program_points')->onDelete('cascade');
            $table->foreignId('child_id')->constrained('event_template_program_points')->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->unique(['parent_id', 'child_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_template_program_point_parent');
    }
}
