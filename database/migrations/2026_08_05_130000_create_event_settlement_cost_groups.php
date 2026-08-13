<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_settlement_cost_groups')) {
            Schema::create('event_settlement_cost_groups', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('settlement_id')->constrained('event_settlements')->cascadeOnDelete();
                $table->string('name');
                $table->string('key', 50)->nullable(); // system: transport|accommodation|program|insurance|other
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_system')->default(false);
                $table->timestamps();

                $table->unique(['settlement_id', 'key']);
                $table->index(['settlement_id', 'sort_order']);
            });
        }

        if (Schema::hasTable('event_settlement_costs') && ! Schema::hasColumn('event_settlement_costs', 'finance_group_id')) {
            Schema::table('event_settlement_costs', function (Blueprint $table): void {
                $table->foreignId('finance_group_id')
                    ->nullable()
                    ->after('contractor_id')
                    ->constrained('event_settlement_cost_groups')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_settlement_costs') && Schema::hasColumn('event_settlement_costs', 'finance_group_id')) {
            Schema::table('event_settlement_costs', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('finance_group_id');
            });
        }

        Schema::dropIfExists('event_settlement_cost_groups');
    }
};
