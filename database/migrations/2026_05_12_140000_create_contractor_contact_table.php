<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contractor_contact')) {
            return;
        }

        Schema::create('contractor_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contractor_id', 'contact_id'], 'contractor_contact_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_contact');
    }
};
