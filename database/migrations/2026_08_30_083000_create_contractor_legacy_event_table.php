<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contractor_legacy_event')) {
            return;
        }

        Schema::create('contractor_legacy_event', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->foreignId('legacy_event_id')->constrained('legacy_events')->cascadeOnDelete();
            $table->string('role', 32)->default('executor'); // purchaser|executor|payment
            $table->string('type_name', 100)->nullable();
            $table->timestamps();

            $table->unique(['contractor_id', 'legacy_event_id'], 'contractor_legacy_event_unique');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_legacy_event');
    }
};
