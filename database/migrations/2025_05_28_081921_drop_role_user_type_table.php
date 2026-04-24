<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('role_user_type');
    }

    public function down(): void
    {
        // Przywrócenie tabeli jeśli potrzeba
    }
};
