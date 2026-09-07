<?php

use App\Actions\Currencies\MergeDuplicateCurrenciesAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('currencies')) {
            return;
        }

        app(MergeDuplicateCurrenciesAction::class)(dryRun: false);

        if (! Schema::hasIndex('currencies', 'currencies_symbol_unique')) {
            Schema::table('currencies', function (Blueprint $table): void {
                $table->unique('symbol');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('currencies')) {
            return;
        }

        if (Schema::hasIndex('currencies', 'currencies_symbol_unique')) {
            Schema::table('currencies', function (Blueprint $table): void {
                $table->dropUnique(['symbol']);
            });
        }
    }
};
