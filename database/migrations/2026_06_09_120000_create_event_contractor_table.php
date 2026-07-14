<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_contractor')) {
            return;
        }

        Schema::create('event_contractor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'contractor_id'], 'event_contractor_unique');
        });

        if (Schema::hasColumn('events', 'contractor_id')) {
            DB::table('events')
                ->whereNotNull('contractor_id')
                ->orderBy('id')
                ->get(['id', 'contractor_id'])
                ->each(function ($row): void {
                    DB::table('event_contractor')->insertOrIgnore([
                        'event_id' => $row->id,
                        'contractor_id' => $row->contractor_id,
                        'sort_order' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_contractor');
    }
};
