<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_contractortype', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->foreignId('contractor_type_id')->constrained('contractor_types')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_contractortype');
    }
};
