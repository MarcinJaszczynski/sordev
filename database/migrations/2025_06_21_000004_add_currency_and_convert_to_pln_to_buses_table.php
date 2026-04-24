<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->string('currency', 8)->default('PLN')->after('extra_km_price');
            $table->boolean('convert_to_pln')->default(true)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->dropColumn(['currency', 'convert_to_pln']);
        });
    }
};
