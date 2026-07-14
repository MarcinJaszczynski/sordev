<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_portal_accesses')) {
            return;
        }

        Schema::create('event_portal_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32);
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('event_participant_id')->nullable();
            $table->string('source', 32)->default('admin');
            $table->timestamp('shared_at')->nullable();
            $table->foreignId('shared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id', 'role']);
            $table->index(['user_id', 'revoked_at']);
            $table->index(['event_id', 'role', 'revoked_at']);
        });

        if (Schema::hasTable('contracts')) {
            Schema::table('event_portal_accesses', function (Blueprint $table) {
                $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
            });
        }

        if (Schema::hasTable('event_participants')) {
            Schema::table('event_portal_accesses', function (Blueprint $table) {
                $table->foreign('event_participant_id')->references('id')->on('event_participants')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_portal_accesses');
    }
};
