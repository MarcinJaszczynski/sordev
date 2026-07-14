<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        if (! Schema::hasColumn('tasks', 'source')) {
            Schema::table('tasks', function (Blueprint $table): void {
                $table->string('source', 32)->default('office')->after('priority');
                $table->index('source');
            });
        }

        DB::table('tasks')
            ->whereIn('priority', ['low', 'medium', 'normal'])
            ->orWhereNull('priority')
            ->update(['priority' => 'normal']);

        DB::table('tasks')
            ->where('priority', 'high')
            ->update(['priority' => 'urgent']);

        DB::table('tasks')
            ->whereNotIn('priority', ['normal', 'urgent'])
            ->update(['priority' => 'normal']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        DB::table('tasks')
            ->where('priority', 'urgent')
            ->update(['priority' => 'high']);

        DB::table('tasks')
            ->where('priority', 'normal')
            ->update(['priority' => 'medium']);

        if (Schema::hasColumn('tasks', 'source')) {
            Schema::table('tasks', function (Blueprint $table): void {
                $table->dropIndex(['source']);
                $table->dropColumn('source');
            });
        }
    }
};
