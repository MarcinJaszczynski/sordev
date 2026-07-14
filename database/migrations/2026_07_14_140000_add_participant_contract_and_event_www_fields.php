<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('events')) {
            if (! Schema::hasColumn('events', 'return_time')) {
                Schema::table('events', function (Blueprint $table) {
                    if (Schema::hasColumn('events', 'departure_time')) {
                        $table->string('return_time', 8)->nullable()->after('departure_time');
                    } else {
                        $table->string('return_time', 8)->nullable();
                    }
                });
            }

            if (! Schema::hasColumn('events', 'www_extra_info')) {
                Schema::table('events', function (Blueprint $table) {
                    foreach (['notes', 'office_notes', 'pilot_notes', 'driver_notes', 'description', 'name'] as $anchor) {
                        if (Schema::hasColumn('events', $anchor)) {
                            $table->text('www_extra_info')->nullable()->after($anchor);

                            return;
                        }
                    }

                    $table->text('www_extra_info')->nullable();
                });
            }
        }

        if (Schema::hasTable('contracts')) {
            if (! Schema::hasColumn('contracts', 'gender')) {
                Schema::table('contracts', function (Blueprint $table) {
                    if (Schema::hasColumn('contracts', 'participant_name')) {
                        $table->string('gender', 16)->nullable()->after('participant_name');
                    } else {
                        $table->string('gender', 16)->nullable();
                    }
                });
            }

            if (! Schema::hasColumn('contracts', 'identification_type')) {
                Schema::table('contracts', function (Blueprint $table) {
                    if (Schema::hasColumn('contracts', 'gender')) {
                        $table->string('identification_type', 32)->nullable()->after('gender');
                    } else {
                        $table->string('identification_type', 32)->nullable();
                    }
                });
            }

            if (! Schema::hasColumn('contracts', 'requires_diet')) {
                Schema::table('contracts', function (Blueprint $table) {
                    if (Schema::hasColumn('contracts', 'identification_type')) {
                        $table->boolean('requires_diet')->default(false)->after('identification_type');
                    } else {
                        $table->boolean('requires_diet')->default(false);
                    }
                });
            }

            if (! Schema::hasColumn('contracts', 'diet_type')) {
                Schema::table('contracts', function (Blueprint $table) {
                    if (Schema::hasColumn('contracts', 'requires_diet')) {
                        $table->string('diet_type')->nullable()->after('requires_diet');
                    } else {
                        $table->string('diet_type')->nullable();
                    }
                });
            }

            if (! Schema::hasColumn('contracts', 'diet_daily_pln')) {
                Schema::table('contracts', function (Blueprint $table) {
                    if (Schema::hasColumn('contracts', 'diet_type')) {
                        $table->decimal('diet_daily_pln', 8, 2)->nullable()->after('diet_type');
                    } else {
                        $table->decimal('diet_daily_pln', 8, 2)->nullable();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                foreach (['return_time', 'www_extra_info'] as $column) {
                    if (Schema::hasColumn('events', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                foreach (['gender', 'identification_type', 'requires_diet', 'diet_type', 'diet_daily_pln'] as $column) {
                    if (Schema::hasColumn('contracts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
