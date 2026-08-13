<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('online_payment_sessions')) {
            Schema::create('online_payment_sessions', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->string('driver', 32);
                $table->string('status', 32)->default('pending'); // pending|paid|failed|expired|cancelled
                $table->string('payable_type');
                $table->unsignedBigInteger('payable_id');
                $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('PLN');
                $table->string('description')->nullable();
                $table->string('payer_email')->nullable();
                $table->string('external_id')->nullable()->index();
                $table->string('checkout_url', 1024)->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['payable_type', 'payable_id']);
                $table->index(['status', 'expires_at']);
            });
        }

        if (! Schema::hasTable('mail_templates')) {
            Schema::create('mail_templates', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->string('subject');
                $table->text('body_html');
                $table->json('placeholders')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_reminder_logs')) {
            Schema::create('payment_reminder_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('event_settlement_participant_payment_id');
                $table->string('channel', 32)->default('email');
                $table->string('recipient')->nullable();
                $table->unsignedSmallInteger('cadence_day')->nullable();
                $table->string('status', 32)->default('queued'); // queued|sent|failed|skipped
                $table->text('message')->nullable();
                $table->timestamps();

                $table->index(
                    ['event_settlement_participant_payment_id', 'created_at'],
                    'payment_reminder_logs_payment_created_idx'
                );

                // Krótka nazwa FK — MySQL limit 64 znaki na identyfikator.
                if (Schema::hasTable('event_settlement_participant_payments')) {
                    $table->foreign('event_settlement_participant_payment_id', 'payment_reminder_logs_payment_fk')
                        ->references('id')
                        ->on('event_settlement_participant_payments')
                        ->cascadeOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reminder_logs');
        Schema::dropIfExists('mail_templates');
        Schema::dropIfExists('online_payment_sessions');
    }
};
