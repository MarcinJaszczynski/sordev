<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_templates') && ! Schema::hasColumn('contract_templates', 'default_attachments')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->json('default_attachments')->nullable()->after('content');
            });
        }

        if (! Schema::hasTable('contract_settings')) {
            Schema::create('contract_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->json('value');
                $table->timestamps();
            });
        }

        $defaultAttachments = [
            'dokumenty/Warunki-Uczestnictwa-2026.pdf',
            'dokumenty/regulamin_przewozu_osób.pdf',
        ];

        if (Schema::hasTable('contract_settings')) {
            DB::table('contract_settings')->updateOrInsert(
                ['key' => 'default_attachments'],
                [
                    'value' => json_encode($defaultAttachments),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_settings');

        if (Schema::hasTable('contract_templates') && Schema::hasColumn('contract_templates', 'default_attachments')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->dropColumn('default_attachments');
            });
        }
    }
};
