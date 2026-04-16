<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('settlement_cost_id')->nullable();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->string('booking_reference')->nullable()->unique();
            $table->integer('participant_count')->default(1);
            $table->decimal('reserved_amount', 10, 2)->nullable();
            $table->enum('status', ['pending', 'confirmed', 'partially_confirmed', 'cancelled', 'completed'])->default('pending');
            $table->dateTime('reserved_at');
            $table->dateTime('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('settlement_cost_id')->references('id')->on('event_settlement_costs')->onDelete('set null');
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('contractor_id')->references('id')->on('contractors')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
