<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_documents', function (Blueprint $table) {
            $table->boolean('is_offer')
                ->default(false)
                ->after('approval_status')
                ->index();

            $table->string('offer_status', 30)
                ->default('draft')
                ->after('is_offer')
                ->index();

            $table->timestamp('offer_sent_at')
                ->nullable()
                ->after('offer_status');

            $table->timestamp('offer_response_at')
                ->nullable()
                ->after('offer_sent_at');

            $table->text('offer_response_notes')
                ->nullable()
                ->after('offer_response_at');

            $table->text('offer_modification_notes')
                ->nullable()
                ->after('offer_response_notes');
        });
    }

    public function down(): void
    {
        Schema::table('event_documents', function (Blueprint $table) {
            $table->dropColumn([
                'is_offer',
                'offer_status',
                'offer_sent_at',
                'offer_response_at',
                'offer_response_notes',
                'offer_modification_notes',
            ]);
        });
    }
};
