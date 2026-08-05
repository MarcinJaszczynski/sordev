<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_participants')) {
            Schema::table('event_participants', function (Blueprint $table): void {
                if (! Schema::hasColumn('event_participants', 'diet')) {
                    $table->string('diet', 255)->nullable()->after('notes');
                }
                if (! Schema::hasColumn('event_participants', 'parent_consent_at')) {
                    $table->timestamp('parent_consent_at')->nullable()->after('diet');
                }
                if (! Schema::hasColumn('event_participants', 'parent_consent_ip')) {
                    $table->string('parent_consent_ip', 45)->nullable()->after('parent_consent_at');
                }
            });
        }

        if (Schema::hasTable('event_hotel_room_occupants')
            && ! Schema::hasColumn('event_hotel_room_occupants', 'event_participant_id')) {
            Schema::table('event_hotel_room_occupants', function (Blueprint $table): void {
                $table->foreignId('event_participant_id')
                    ->nullable()
                    ->after('contract_id')
                    ->constrained('event_participants')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('event_attendances')) {
            Schema::create('event_attendances', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $table->foreignId('event_participant_id')->constrained('event_participants')->cascadeOnDelete();
                $table->unsignedSmallInteger('day')->default(1);
                $table->string('status', 20)->default('unknown'); // present|absent|unknown
                $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['event_id', 'event_participant_id', 'day'], 'event_attendances_unique_day');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendances');

        if (Schema::hasTable('event_hotel_room_occupants')
            && Schema::hasColumn('event_hotel_room_occupants', 'event_participant_id')) {
            Schema::table('event_hotel_room_occupants', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('event_participant_id');
            });
        }

        if (Schema::hasTable('event_participants')) {
            Schema::table('event_participants', function (Blueprint $table): void {
                foreach (['parent_consent_ip', 'parent_consent_at', 'diet'] as $column) {
                    if (Schema::hasColumn('event_participants', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
