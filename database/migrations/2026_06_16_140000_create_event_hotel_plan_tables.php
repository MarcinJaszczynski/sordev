<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_hotel_stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('day');
            $table->foreignId('contractor_id')->nullable()->constrained('contractors')->nullOnDelete();
            $table->foreignId('event_program_point_id')->nullable()->constrained('event_program_points')->nullOnDelete();
            $table->text('offer_notes')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('same_as_day')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'day']);
        });

        Schema::create('event_hotel_room_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_hotel_stay_id')->constrained('event_hotel_stays')->cascadeOnDelete();
            $table->foreignId('hotel_room_id')->nullable()->constrained('hotel_rooms')->nullOnDelete();
            $table->string('label')->nullable();
            $table->string('role', 16)->default('qty');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->boolean('convert_to_pln')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('event_hotel_room_occupants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_hotel_room_line_id')->constrained('event_hotel_room_lines')->cascadeOnDelete();
            $table->string('name');
            $table->string('source', 16)->default('manual');
            $table->unsignedBigInteger('event_agreement_id')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_hotel_room_occupants');
        Schema::dropIfExists('event_hotel_room_lines');
        Schema::dropIfExists('event_hotel_stays');
    }
};
