<?php

use App\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $oldStatuses = array_keys(Event::LEGACY_STATUS_MIGRATION_MAP);
        $newStatuses = array_keys(Event::getStatusOptions());

        Schema::table('events', function (Blueprint $table) use ($oldStatuses, $newStatuses) {
            $table->enum('status', array_values(array_unique(array_merge($oldStatuses, $newStatuses))))
                ->default(Event::STATUS_INQUIRY)
                ->change();
        });

        foreach (Event::LEGACY_STATUS_MIGRATION_MAP as $oldStatus => $newStatus) {
            DB::table('events')
                ->where('status', $oldStatus)
                ->update(['status' => $newStatus]);
        }

        Schema::table('events', function (Blueprint $table) use ($newStatuses) {
            $table->enum('status', $newStatuses)
                ->default(Event::STATUS_INQUIRY)
                ->change();
        });
    }

    public function down(): void
    {
        $oldStatuses = array_keys(Event::LEGACY_STATUS_MIGRATION_MAP);
        $newStatuses = array_keys(Event::getStatusOptions());
        $reverseMap = [
            Event::STATUS_INQUIRY => 'draft',
            Event::STATUS_OFFER => 'draft',
            Event::STATUS_PROVISIONAL_RESERVATION => 'draft',
            Event::STATUS_CONFIRMED => 'confirmed',
            Event::STATUS_TO_SETTLE => 'in_progress',
            Event::STATUS_SETTLED => 'completed',
            Event::STATUS_PENDING_CANCELLATION => 'cancelled',
            Event::STATUS_CANCELLED => 'cancelled',
        ];

        Schema::table('events', function (Blueprint $table) use ($oldStatuses, $newStatuses) {
            $table->enum('status', array_values(array_unique(array_merge($oldStatuses, $newStatuses))))
                ->default('draft')
                ->change();
        });

        foreach ($reverseMap as $newStatus => $oldStatus) {
            DB::table('events')
                ->where('status', $newStatus)
                ->update(['status' => $oldStatus]);
        }

        Schema::table('events', function (Blueprint $table) use ($oldStatuses) {
            $table->enum('status', $oldStatuses)
                ->default('draft')
                ->change();
        });
    }
};
