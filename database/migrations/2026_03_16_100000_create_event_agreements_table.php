<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('contract_template_id')->nullable()->constrained('contract_templates')->nullOnDelete();

            $table->string('title');
            $table->string('agreement_number')->nullable();
            $table->date('agreement_date')->nullable();

            $table->string('event_name')->nullable();
            $table->date('event_start_date')->nullable();
            $table->date('event_end_date')->nullable();

            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->unsignedInteger('participant_count')->nullable();

            $table->decimal('amount_due', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('currency', 10)->default('PLN');

            $table->string('status')->default('draft')->comment('draft|sent|signed|completed|cancelled');
            $table->string('payment_status')->default('pending')->comment('pending|paid|failed');
            $table->string('payment_method')->nullable()->comment('demo_transfer|demo_card|demo_blik|other');

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

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'payment_status']);
            $table->index(['agreement_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_agreements');
    }
};
