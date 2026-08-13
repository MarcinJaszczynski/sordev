<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_trip_inquiries')) {
            return;
        }

        Schema::create('client_trip_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('event_portal_access_id')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->string('status', 32)->default('open');
            $table->text('office_reply')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        if (Schema::hasTable('contracts')) {
            Schema::table('client_trip_inquiries', function (Blueprint $table) {
                $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
            });
        }

        if (Schema::hasTable('event_portal_accesses')) {
            Schema::table('client_trip_inquiries', function (Blueprint $table) {
                $table->foreign('event_portal_access_id')->references('id')->on('event_portal_accesses')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_trip_inquiries');
    }
};
