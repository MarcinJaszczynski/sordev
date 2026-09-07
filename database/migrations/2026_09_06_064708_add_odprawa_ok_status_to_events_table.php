<?php

use App\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $statuses = array_keys(Event::getStatusOptions());

        Schema::table('events', function (Blueprint $table) use ($statuses) {
            $table->enum('status', $statuses)
                ->default(Event::STATUS_INQUIRY)
                ->change();
        });
    }

    public function down(): void
    {
        $statuses = array_values(array_filter(
            array_keys(Event::getStatusOptions()),
            fn (string $status): bool => $status !== Event::STATUS_ODPRAWA_OK,
        ));

        // Najpierw rozszerzony zestaw (żeby wolno było przepisać odprawa_ok → confirmed).
        Schema::table('events', function (Blueprint $table): void {
            $table->enum('status', array_keys(Event::getStatusOptions()))
                ->default(Event::STATUS_INQUIRY)
                ->change();
        });

        \Illuminate\Support\Facades\DB::table('events')
            ->where('status', Event::STATUS_ODPRAWA_OK)
            ->update(['status' => Event::STATUS_CONFIRMED]);

        Schema::table('events', function (Blueprint $table) use ($statuses) {
            $table->enum('status', $statuses)
                ->default(Event::STATUS_INQUIRY)
                ->change();
        });
    }
};
