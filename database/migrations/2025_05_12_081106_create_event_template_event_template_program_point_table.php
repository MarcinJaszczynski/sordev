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
        if (! Schema::hasTable('event_template_event_template_program_point')) {
            Schema::create('event_template_event_template_program_point', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_template_id');
                $table->unsignedBigInteger('event_template_program_point_id');
                $table->foreign('event_template_id', 'etepp_et_fk')
                    ->references('id')
                    ->on('event_templates')
                    ->onDelete('cascade');
                $table->foreign('event_template_program_point_id', 'etepp_pp_fk')
                    ->references('id')
                    ->on('event_template_program_points')
                    ->onDelete('cascade');
                $table->unsignedInteger('day')->default(1);
                $table->integer('order')->default(0); // Pole do przechowywania kolejności
                $table->text('notes')->nullable();
                $table->boolean('include_in_program')->default(true);
                $table->boolean('include_in_calculation')->default(true);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_template_event_template_program_point');
    }
};
