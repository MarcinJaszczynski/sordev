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
        Schema::table('event_template_program_points', function (Blueprint $table) {
            //
                        $table->boolean('convert_to_pln')->default(true); // Domyślnie "tak"

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_template_program_points', function (Blueprint $table) {
            //
                        $table->dropColumn('convert_to_pln');

        });
    }
};
