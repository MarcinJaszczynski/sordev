<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')
                ->nullable()
                ->constrained('contractors')
                ->nullOnDelete();
            $table->string('type', 32)->default('bus');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('registration_number', 32);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->json('equipment')->nullable();
            $table->string('status', 32)->default('active');
            $table->boolean('is_ad_hoc')->default(false);
            $table->string('primary_image')->nullable();
            $table->json('gallery')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['contractor_id', 'registration_number']);
            $table->index(['status', 'type']);
            $table->index('is_ad_hoc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
