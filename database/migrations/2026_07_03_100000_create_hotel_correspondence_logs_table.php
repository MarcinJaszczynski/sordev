<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hotel_correspondence_logs')) {
            return;
        }

        Schema::create('hotel_correspondence_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('contractor_id')->nullable()->constrained('contractors')->nullOnDelete();
            $table->unsignedBigInteger('event_hotel_stay_id')->nullable();
            $table->string('direction', 16)->default('outbound');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('contact_person')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'contacted_at']);
        });

        if (Schema::hasTable('event_hotel_stays')) {
            Schema::table('hotel_correspondence_logs', function (Blueprint $table) {
                $table->foreign('event_hotel_stay_id')
                    ->references('id')
                    ->on('event_hotel_stays')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_correspondence_logs');
    }
};
