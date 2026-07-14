<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('legacy_id')->unique();
            $table->string('office_id', 100)->nullable();
            $table->string('name');
            $table->string('legacy_status', 100)->nullable();

            $table->timestamp('start_datetime')->nullable();
            $table->timestamp('end_datetime')->nullable();
            $table->integer('duration_days')->nullable();

            $table->integer('participant_count')->nullable();
            $table->integer('guardians_count')->nullable();
            $table->integer('free_count')->nullable();

            $table->string('client_name', 500)->nullable();
            $table->string('client_street', 500)->nullable();
            $table->string('client_city', 500)->nullable();
            $table->string('client_nip', 100)->nullable();
            $table->string('client_contact_person', 500)->nullable();
            $table->string('client_phone', 200)->nullable();
            $table->string('client_email', 500)->nullable();

            $table->text('pilot')->nullable();
            $table->text('driver')->nullable();
            $table->timestamp('bus_board_time')->nullable();
            $table->integer('advance_payment')->nullable();

            $table->text('notes')->nullable();
            $table->text('start_description')->nullable();
            $table->text('end_description')->nullable();
            $table->text('diet_alert')->nullable();
            $table->text('pilot_notes')->nullable();
            $table->text('order_note')->nullable();

            $table->unsignedBigInteger('legacy_purchaser_id')->nullable();
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->foreign('contractor_id')->references('id')->on('contractors')->nullOnDelete();

            $table->json('elements_json')->nullable();
            $table->json('contractors_json')->nullable();
            $table->json('payments_json')->nullable();
            $table->json('notes_json')->nullable();

            $table->timestamps();

            $table->index('legacy_status');
            $table->index('start_datetime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_events');
    }
};
