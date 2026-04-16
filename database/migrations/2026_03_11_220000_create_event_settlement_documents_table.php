<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_settlement_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('event_settlements')->cascadeOnDelete();
            $table->string('document_type')->default('invoice')->comment('invoice|receipt|other');
            $table->string('document_number')->nullable();
            $table->string('vendor_name')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->date('issue_date')->nullable();
            $table->timestamp('payment_date')->nullable();
            $table->string('payment_method')->nullable()->comment('cash|transfer|card|other');
            $table->string('payer_scope')->nullable()->comment('office|pilot');
            $table->json('linked_cost_ids')->nullable()->comment('ID kosztów/punktów programu podpiętych do dokumentu');
            $table->json('files')->nullable()->comment('Skany, zdjęcia faktur i inne załączniki');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['settlement_id', 'document_type']);
            $table->index(['settlement_id', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_settlement_documents');
    }
};
