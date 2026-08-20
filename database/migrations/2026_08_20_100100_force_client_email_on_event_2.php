<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasColumn('events', 'client_email')) {
            return;
        }

        DB::table('events')
            ->where('id', 2)
            ->update([
                'client_email' => 'anetaprzyklad@gmail.com',
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasColumn('events', 'client_email')) {
            return;
        }

        DB::table('events')
            ->where('id', 2)
            ->where('client_email', 'anetaprzyklad@gmail.com')
            ->update([
                'client_email' => null,
            ]);
    }
};
