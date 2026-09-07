<?php

declare(strict_types=1);

use App\Actions\Currencies\MergeDuplicateCurrenciesAction;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Migracja zakłada unique(symbol) — na potrzeby testu merge zdejmujemy constraint.
    if (Schema::hasIndex('currencies', 'currencies_symbol_unique')) {
        Schema::table('currencies', function (Blueprint $table): void {
            $table->dropUnique(['symbol']);
        });
    }
});

it('merges duplicate currency symbols and rewrites foreign keys', function (): void {
    $plnKeeper = Currency::factory()->create([
        'name' => 'Polski złoty',
        'symbol' => 'PLN',
        'exchange_rate' => 1,
    ]);
    $plnDupe = Currency::factory()->create([
        'name' => 'Polski Złoty',
        'symbol' => 'PLN',
        'exchange_rate' => 1,
    ]);
    $eurKeeper = Currency::factory()->create([
        'name' => 'Euro',
        'symbol' => 'EUR',
        'exchange_rate' => 4.35,
    ]);
    Currency::factory()->create([
        'name' => 'Euro',
        'symbol' => 'EUR',
        'exchange_rate' => 4.50,
    ]);

    $event = Event::factory()->create();
    $point = EventProgramPoint::factory()->create([
        'event_id' => $event->id,
        'currency_id' => $plnDupe->id,
    ]);

    $report = app(MergeDuplicateCurrenciesAction::class)(dryRun: false);

    expect($report['groups'])->toHaveCount(2)
        ->and(Currency::query()->where('symbol', 'PLN')->count())->toBe(1)
        ->and(Currency::query()->where('symbol', 'EUR')->count())->toBe(1)
        ->and(Currency::query()->whereKey($plnKeeper->id)->exists())->toBeTrue()
        ->and(Currency::query()->whereKey($eurKeeper->id)->exists())->toBeTrue()
        ->and($point->fresh()->currency_id)->toBe($plnKeeper->id);
});

it('dry-run does not delete duplicates', function (): void {
    Currency::factory()->create(['symbol' => 'PLN', 'name' => 'A', 'exchange_rate' => 1]);
    Currency::factory()->create(['symbol' => 'PLN', 'name' => 'B', 'exchange_rate' => 1]);

    app(MergeDuplicateCurrenciesAction::class)(dryRun: true);

    expect(Currency::query()->where('symbol', 'PLN')->count())->toBe(2);
});
