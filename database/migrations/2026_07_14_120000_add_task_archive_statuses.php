<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_statuses')) {
            return;
        }

        (new \Database\Seeders\TaskStatusSeeder)->run();
    }

    public function down(): void
    {
        // Statusy pozostają w bazie — nie usuwamy rekordów historycznych.
    }
};
