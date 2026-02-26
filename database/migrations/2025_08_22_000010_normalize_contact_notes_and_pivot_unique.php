<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize notes: if 'note' exists and 'notes' does not, rename
        if (Schema::hasTable('contacts')) {
            $cols = Schema::getColumnListing('contacts');
            if (in_array('note', $cols, true) && !in_array('notes', $cols, true)) {
                Schema::table('contacts', function (Blueprint $table) {
                    $table->renameColumn('note', 'notes');
                });
            } elseif (!in_array('notes', $cols, true) && !in_array('note', $cols, true)) {
                // neither exists - create notes
                Schema::table('contacts', function (Blueprint $table) {
                    $table->text('notes')->nullable();
                });
            }
        }

        // Add unique index on pivot contractor_contact(contractor_id, contact_id)
        if (Schema::hasTable('contractor_contact')) {
            $indexes = DB::select("PRAGMA index_list('contractor_contact')");
            $hasUnique = false;
            foreach ($indexes as $idx) {
                if (strpos($idx->name, 'contractor_contact_unique') !== false) {
                    $hasUnique = true;
                    break;
                }
            }

            if (!$hasUnique) {
                Schema::table('contractor_contact', function (Blueprint $table) {
                    $table->unique(['contractor_id', 'contact_id'], 'contractor_contact_unique');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contacts')) {
            $cols = Schema::getColumnListing('contacts');
            if (in_array('notes', $cols, true) && !in_array('note', $cols, true)) {
                Schema::table('contacts', function (Blueprint $table) {
                    $table->renameColumn('notes', 'note');
                });
            }
        }

        if (Schema::hasTable('contractor_contact')) {
            Schema::table('contractor_contact', function (Blueprint $table) {
                $table->dropUnique('contractor_contact_unique');
            });
        }
    }
};
