<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('contract_templates', 'custom_placeholders')) {
                $table->json('custom_placeholders')->nullable()->after('content');
            }

            if (! Schema::hasColumn('contract_templates', 'applies_to')) {
                $table->json('applies_to')->nullable()->after('custom_placeholders');
            }

            if (! Schema::hasColumn('contract_templates', 'version_group_id')) {
                $table->uuid('version_group_id')->nullable()->after('applies_to')->index();
            }

            if (! Schema::hasColumn('contract_templates', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('version_group_id');
            }

            if (! Schema::hasColumn('contract_templates', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('version');
            }

            if (! Schema::hasColumn('contract_templates', 'version_notes')) {
                $table->text('version_notes')->nullable()->after('is_active');
            }
        });

        // Istniejące szablony: każda linia wersji dostaje własną grupę.
        foreach (DB::table('contract_templates')->whereNull('version_group_id')->orderBy('id')->get() as $row) {
            DB::table('contract_templates')
                ->where('id', $row->id)
                ->update([
                    'version_group_id' => (string) Str::uuid(),
                    'version' => 1,
                    'is_active' => true,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            foreach (['version_notes', 'is_active', 'version', 'version_group_id', 'applies_to', 'custom_placeholders'] as $column) {
                if (Schema::hasColumn('contract_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
