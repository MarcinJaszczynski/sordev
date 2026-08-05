<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_invoices')) {
            return;
        }

        Schema::create('sales_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('client_invoice_request_id')->nullable()->constrained('client_invoice_requests')->nullOnDelete();
            $table->string('number')->nullable();
            $table->string('type', 30)->default('final'); // proforma|advance|final
            $table->string('procedure', 30)->default('vat_margin');
            $table->string('status', 30)->default('draft'); // draft|issued_local
            $table->string('buyer_name')->nullable();
            $table->string('buyer_nip', 20)->nullable();
            $table->string('buyer_address')->nullable();
            $table->decimal('revenue_pln', 12, 2)->default(0);
            $table->decimal('cost_pln', 12, 2)->default(0);
            $table->decimal('margin_gross_pln', 12, 2)->default(0);
            $table->decimal('margin_net_pln', 12, 2)->default(0);
            $table->decimal('vat_on_margin_pln', 12, 2)->default(0);
            $table->string('currency', 3)->default('PLN');
            $table->string('ksef_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sales_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price_pln', 12, 2)->default(0);
            $table->decimal('total_pln', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_lines');
        Schema::dropIfExists('sales_invoices');
    }
};
