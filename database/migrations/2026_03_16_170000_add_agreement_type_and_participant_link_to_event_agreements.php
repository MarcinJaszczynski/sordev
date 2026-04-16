<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_agreements', function (Blueprint $table) {
            $table->string('agreement_type', 20)->default('group');
            $table->unsignedBigInteger('participant_payment_id')->nullable();
            $table->string('participant_name')->nullable();
            $table->date('participant_birth_date')->nullable();
            $table->string('participant_email')->nullable();
            $table->string('participant_phone')->nullable();

            $table->index(['event_id', 'agreement_type'], 'event_agreements_event_type_idx');
            $table->index('participant_payment_id', 'event_agreements_participant_payment_idx');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('event_agreements', function (Blueprint $table) {
                $table->foreign('participant_payment_id', 'event_agreements_participant_payment_fk')
                    ->references('id')
                    ->on('event_settlement_participant_payments')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('event_agreements', function (Blueprint $table) {
                $table->dropForeign('event_agreements_participant_payment_fk');
            });
        }

        Schema::table('event_agreements', function (Blueprint $table) {
            $table->dropIndex('event_agreements_event_type_idx');
            $table->dropIndex('event_agreements_participant_payment_idx');
            $table->dropColumn([
                'agreement_type',
                'participant_payment_id',
                'participant_name',
                'participant_birth_date',
                'participant_email',
                'participant_phone',
            ]);
        });
    }
};
