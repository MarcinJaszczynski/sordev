<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite cannot reliably add FK constraints with ALTER TABLE.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('status_id', 'tasks_status_id_fk')
                ->references('id')
                ->on('task_statuses')
                ->cascadeOnDelete();
        });

        Schema::table('task_attachments', function (Blueprint $table) {
            $table->foreign('task_id', 'task_attachments_task_id_fk')
                ->references('id')
                ->on('tasks')
                ->cascadeOnDelete();
        });

        Schema::table('task_comments', function (Blueprint $table) {
            $table->foreign('task_id', 'task_comments_task_id_fk')
                ->references('id')
                ->on('tasks')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropForeign('task_comments_task_id_fk');
        });

        Schema::table('task_attachments', function (Blueprint $table) {
            $table->dropForeign('task_attachments_task_id_fk');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign('tasks_status_id_fk');
        });
    }
};
