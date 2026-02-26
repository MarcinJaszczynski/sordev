<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            if (!Schema::hasColumn('contractors', 'region')) {
                $table->string('region')->nullable();
            }
            if (!Schema::hasColumn('contractors', 'country')) {
                $table->string('country')->nullable();
            }
            if (!Schema::hasColumn('contractors', 'nip')) {
                $table->string('nip')->nullable();
            }
            if (!Schema::hasColumn('contractors', 'www')) {
                $table->string('www')->nullable();
            }
            if (!Schema::hasColumn('contractors', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('contractors', 'firstname')) {
                $table->string('firstname')->nullable();
            }
            if (!Schema::hasColumn('contractors', 'surname')) {
                $table->string('surname')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn(['region','country','nip','www','description','firstname','surname']);
        });
    }
};
