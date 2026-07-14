<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tfg_dictionary_items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index();
            $table->string('code', 50);
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['type', 'code']);
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('contract_template_id')->nullable()->constrained('contract_templates')->nullOnDelete();
            $table->unsignedBigInteger('legacy_event_agreement_id')->nullable()->index();

            $table->string('contract_type')->default('group')->comment('group|individual|template');
            $table->foreignId('participant_payment_id')->nullable()->constrained('event_settlement_participant_payments')->nullOnDelete();

            $table->string('title')->default('Umowa imprezy');
            $table->string('contract_number')->nullable()->unique();
            $table->string('reservation_number')->nullable();
            $table->date('contract_date')->nullable();
            $table->string('subject_code')->nullable();
            $table->string('payment_method_code')->nullable();

            $table->string('event_name')->nullable();
            $table->date('event_start_date')->nullable();
            $table->date('event_end_date')->nullable();

            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('participant_name')->nullable();
            $table->date('participant_birth_date')->nullable();
            $table->string('participant_email')->nullable();
            $table->string('participant_phone')->nullable();
            $table->unsignedInteger('participant_count')->nullable();

            $table->decimal('total_price', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('currency', 10)->default('PLN');

            $table->string('status')->default('draft');
            $table->string('payment_status')->default('pending');
            $table->string('client_payment_method')->nullable();

            $table->string('signer_name')->nullable();
            $table->string('signer_email')->nullable();
            $table->string('signer_phone')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->string('public_token')->unique();
            $table->timestamp('public_token_expires_at')->nullable();

            $table->longText('agreement_body')->nullable();
            $table->json('attachments')->nullable();
            $table->text('admin_notes')->nullable();
            $table->json('meta')->nullable();

            $table->string('tfg_status')->nullable();
            $table->string('pending_operation')->nullable();
            $table->string('correction_reason')->nullable();
            $table->foreignId('last_feed_log_id')->nullable();
            $table->timestamp('tfg_synced_at')->nullable();
            $table->timestamp('tfg_update_deadline_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'contract_type']);
            $table->index(['tfg_status', 'pending_operation']);
        });

        Schema::create('contract_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('travelers_count')->default(1);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_variant_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_variant_id')->constrained('contract_variants')->cascadeOnDelete();
            $table->string('scope_type')->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('locality')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('contract_variant_transports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_variant_id')->constrained('contract_variants')->cascadeOnDelete();
            $table->string('transport_code', 50);
            $table->json('icao_codes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('contract_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('PLN');
            $table->date('paid_at')->nullable();
            $table->string('payment_method_code')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('participant_payment_id')->nullable()->constrained('event_settlement_participant_payments')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('contract_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('PLN');
            $table->date('refunded_at')->nullable();
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tfg_feed_logs', function (Blueprint $table) {
            $table->id();
            $table->string('feed_identifier')->nullable()->unique();
            $table->string('operation_type')->nullable();
            $table->unsignedInteger('contracts_count')->default(0);
            $table->string('sync_status')->nullable();
            $table->json('sync_errors')->nullable();
            $table->string('async_status')->nullable();
            $table->json('async_errors')->nullable();
            $table->string('payload_path')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->unsignedTinyInteger('payload_version')->default(1);
            $table->unsignedSmallInteger('poll_attempts')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tfg_feed_log_contract', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tfg_feed_log_id')->constrained('tfg_feed_logs')->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->string('operation')->nullable();
            $table->string('correction_reason')->nullable();
            $table->timestamps();
            $table->unique(['tfg_feed_log_id', 'contract_id']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('last_feed_log_id')->references('id')->on('tfg_feed_logs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['last_feed_log_id']);
        });
        Schema::dropIfExists('tfg_feed_log_contract');
        Schema::dropIfExists('tfg_feed_logs');
        Schema::dropIfExists('contract_refunds');
        Schema::dropIfExists('contract_payments');
        Schema::dropIfExists('contract_variant_transports');
        Schema::dropIfExists('contract_variant_locations');
        Schema::dropIfExists('contract_variants');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('tfg_dictionary_items');
    }
};
