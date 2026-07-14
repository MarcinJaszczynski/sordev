<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contract_ordering_parties') && Schema::hasTable('contracts')) {
            Schema::create('contract_ordering_parties', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
                $table->foreignId('contractor_id')->nullable()->constrained('contractors')->nullOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('nip')->nullable();
                $table->string('street')->nullable();
                $table->string('house_number')->nullable();
                $table->string('city')->nullable();
                $table->string('postal_code')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['contract_id', 'sort_order'], 'contract_ordering_parties_contract_sort_idx');
            });
        }

        if (! Schema::hasTable('event_agreement_ordering_parties') && Schema::hasTable('event_agreements')) {
            Schema::create('event_agreement_ordering_parties', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_agreement_id')->constrained('event_agreements')->cascadeOnDelete();
                $table->foreignId('contractor_id')->nullable()->constrained('contractors')->nullOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('nip')->nullable();
                $table->string('street')->nullable();
                $table->string('house_number')->nullable();
                $table->string('city')->nullable();
                $table->string('postal_code')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['event_agreement_id', 'sort_order'], 'ea_ordering_parties_agreement_sort_idx');
            });
        }

        if (Schema::hasTable('contracts') && ! Schema::hasColumn('contracts', 'ordering_party_notes')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->text('ordering_party_notes')->nullable()->after('customer_phone');
            });
        }

        if (Schema::hasTable('event_agreements') && ! Schema::hasColumn('event_agreements', 'ordering_party_notes')) {
            Schema::table('event_agreements', function (Blueprint $table) {
                $table->text('ordering_party_notes')->nullable()->after('customer_phone');
            });
        }

        $this->backfillOrderingParties();
    }

    public function down(): void
    {
        if (Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'ordering_party_notes')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropColumn('ordering_party_notes');
            });
        }

        if (Schema::hasTable('event_agreements') && Schema::hasColumn('event_agreements', 'ordering_party_notes')) {
            Schema::table('event_agreements', function (Blueprint $table) {
                $table->dropColumn('ordering_party_notes');
            });
        }

        Schema::dropIfExists('event_agreement_ordering_parties');
        Schema::dropIfExists('contract_ordering_parties');
    }

    protected function backfillOrderingParties(): void
    {
        if (Schema::hasTable('contracts') && Schema::hasTable('contract_ordering_parties')) {
            DB::table('contracts')
                ->whereNotNull('customer_name')
                ->where('customer_name', '!=', '')
                ->orderBy('id')
                ->get(['id', 'customer_name', 'customer_email', 'customer_phone'])
                ->each(function ($row): void {
                    $exists = DB::table('contract_ordering_parties')
                        ->where('contract_id', $row->id)
                        ->exists();

                    if ($exists) {
                        return;
                    }

                    DB::table('contract_ordering_parties')->insert([
                        'contract_id' => $row->id,
                        'contractor_id' => null,
                        'sort_order' => 0,
                        'name' => $row->customer_name,
                        'email' => $row->customer_email,
                        'phone' => $row->customer_phone,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
        }

        if (Schema::hasTable('event_agreements') && Schema::hasTable('event_agreement_ordering_parties')) {
            DB::table('event_agreements')
                ->whereNotNull('customer_name')
                ->where('customer_name', '!=', '')
                ->orderBy('id')
                ->get(['id', 'customer_name', 'customer_email', 'customer_phone'])
                ->each(function ($row): void {
                    $exists = DB::table('event_agreement_ordering_parties')
                        ->where('event_agreement_id', $row->id)
                        ->exists();

                    if ($exists) {
                        return;
                    }

                    DB::table('event_agreement_ordering_parties')->insert([
                        'event_agreement_id' => $row->id,
                        'contractor_id' => null,
                        'sort_order' => 0,
                        'name' => $row->customer_name,
                        'email' => $row->customer_email,
                        'phone' => $row->customer_phone,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
        }
    }
};
