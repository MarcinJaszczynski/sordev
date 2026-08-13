<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_participants')) {
            return;
        }

        Schema::table('event_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_participants', 'consents')) {
                $table->json('consents')->nullable()->after('parent_consent_ip');
            }
        });

        if (Schema::hasColumn('event_participants', 'parent_consent_at')
            && Schema::hasColumn('event_participants', 'consents')
        ) {
            DB::table('event_participants')
                ->whereNotNull('parent_consent_at')
                ->whereNull('consents')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $at = (string) $row->parent_consent_at;
                        DB::table('event_participants')->where('id', $row->id)->update([
                            'consents' => json_encode([
                                'terms' => $at,
                                'insurance' => $at,
                                'rodo' => $at,
                                'image' => null,
                                'medical' => null,
                            ], JSON_UNESCAPED_UNICODE),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_participants')) {
            return;
        }

        Schema::table('event_participants', function (Blueprint $table): void {
            if (Schema::hasColumn('event_participants', 'consents')) {
                $table->dropColumn('consents');
            }
        });
    }
};
