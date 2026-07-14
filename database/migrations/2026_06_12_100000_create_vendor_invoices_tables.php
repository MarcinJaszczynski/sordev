<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_invoice_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type', 32);
            $table->string('status', 32)->default('pending');
            $table->string('source_filename')->nullable();
            $table->string('source_path')->nullable();
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('unmatched_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('error_log')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->nullable()->constrained('vendor_invoice_import_batches')->nullOnDelete();
            $table->string('source', 32)->default('csv');
            $table->string('ksef_number')->nullable()->unique();
            $table->string('invoice_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('sale_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('received_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('currency', 8)->default('PLN');
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('payment_status', 32)->default('due');
            $table->string('payment_method', 64)->nullable();
            $table->string('approval_status', 32)->default('pending');
            $table->string('matching_status', 32)->default('unmatched');
            $table->string('seller_nip', 20)->nullable();
            $table->string('seller_name')->nullable();
            $table->string('seller_street')->nullable();
            $table->string('seller_post_code', 16)->nullable();
            $table->string('seller_city')->nullable();
            $table->string('seller_country', 8)->nullable();
            $table->string('seller_email')->nullable();
            $table->string('buyer_nip', 20)->nullable();
            $table->string('buyer_name')->nullable();
            $table->foreignId('contractor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_program_point_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_settlement_cost_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('settlement_document_id')->nullable()->constrained('event_settlement_documents')->nullOnDelete();
            $table->boolean('sync_to_settlement')->default(false);
            $table->string('pdf_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('matching_hints')->nullable();
            $table->json('raw_payload')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('seller_nip');
            $table->index('payment_status');
            $table->index('approval_status');
            $table->index('matching_status');
            $table->index('due_date');
            $table->index(['event_id', 'contractor_id']);
        });

        Schema::create('vendor_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_order')->default(0);
            $table->string('name');
            $table->decimal('quantity', 14, 4)->default(1);
            $table->string('unit', 32)->nullable();
            $table->string('vat_rate', 16)->nullable();
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_invoice_lines');
        Schema::dropIfExists('vendor_invoices');
        Schema::dropIfExists('vendor_invoice_import_batches');
    }
};
