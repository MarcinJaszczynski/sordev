<?php
// Skript: scripts/rewrite_prices.php
// Cel: Bezpiecznie usunac stare wpisy i zapisać ceny zgodne z EventTemplateCalculationEngine

use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Models\EventTemplateQty;
use App\Models\Currency;
use App\Services\EventTemplateCalculationEngine; // legacy (opcjonalnie)
use App\Services\PriceRoundingService; // wciąż używane przy legacy fallback
use App\Services\UnifiedPriceCalculator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

ini_set('memory_limit', '512M');

require __DIR__ . '/../vendor/autoload.php';

// Tymczasowo ukryj argumenty CLI, aby kernel Artisan nie próbował ich parsować
$__originalArgv = $_SERVER['argv'] ?? [];
$__originalArgc = $_SERVER['argc'] ?? null;
if (!empty($__originalArgv)) {
    $_SERVER['argv'] = [$__originalArgv[0] ?? 'artisan'];
    $_SERVER['argc'] = 1;
}

// bootstrap aplikacji
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Przywróć oryginalne argumenty (potrzebne dla getopt poniżej)
if (!empty($__originalArgv)) {
    $_SERVER['argv'] = $__originalArgv;
    $_SERVER['argc'] = $__originalArgc ?? count($__originalArgv);
}

// Parametry: --dry (nie zapisuje, tylko podgląd), --limit=N, --only=templateId, --legacy (stary silnik), --keep-existing, --delete-existing
$options = getopt('', ['dry::', 'limit::', 'only::', 'legacy::', 'delete-existing::', 'keep-existing::']);
$dryRun = array_key_exists('dry', $options);
$limit = isset($options['limit']) ? (int)$options['limit'] : null;
$only = isset($options['only']) ? (int)$options['only'] : null;

echo "Dry run: " . ($dryRun ? 'YES' : 'NO') . "\n";

function database_path($file = '')
{
    return __DIR__ . '/../database' . ($file ? DIRECTORY_SEPARATOR . $file : '');
}

$dbPath = database_path('database.sqlite');
$backupPath = database_path('database.sqlite.bak.' . date('Ymd_His'));
if (!$dryRun) {
    echo "Creating DB backup: $backupPath\n";
    copy($dbPath, $backupPath);
}

$legacyMode = array_key_exists('legacy', $options);

// Domyślnie kasujemy istniejące wpisy (żeby nie zostawiać starych walut / wariantów).
// --keep-existing przywraca stare zachowanie upsertu, a --delete-existing wymusza kasowanie.
$keepExisting = array_key_exists('keep-existing', $options);
$deleteExisting = !$keepExisting || array_key_exists('delete-existing', $options);

echo "Delete existing: " . ($deleteExisting ? 'YES' : 'NO') . "\n";
echo "Mode: " . ($legacyMode ? 'LEGACY' : 'UNIFIED') . "\n";

$engine = $legacyMode ? new EventTemplateCalculationEngine() : null;
$unified = $legacyMode ? null : new UnifiedPriceCalculator();

/**
 * Synchronizuje ceny w walutach innych niż PLN z bazową ceną PLN dla danej kombinacji szablonu i miejsca startowego.
 * Jeżeli data aktualizacji różni się od rekordu PLN lub cena jest pusta/<=0, przelicza cenę ponownie.
 * Gdy po przeliczeniu cena wynosi 0 – rekord waluty obcej jest usuwany.
 */
function resyncForeignCurrencyRows(int $templateId, ?int $startPlaceId, bool $dryRun = false, bool $allowForeignCurrencies = true): void
{
    if (!$allowForeignCurrencies) {
        echo "[sync] Pomijam przeliczanie walut obcych (wyłączone dla tego szablonu)\n";
        return;
    }
    $plnCurrencyIds = Currency::plnIds();
    if (empty($plnCurrencyIds)) {
        echo "[sync] Pomijam – brak zdefiniowanej waluty PLN\n";
        return;
    }

    $foreignCurrencies = Currency::whereNotIn('id', $plnCurrencyIds)->get()->keyBy('id');

    $query = DB::table('event_template_price_per_person')
        ->where('event_template_id', $templateId);

    if ($startPlaceId === null) {
        $query->whereNull('start_place_id');
    } else {
        $query->where('start_place_id', $startPlaceId);
    }

    $processGroup = function (array $entries) use ($plnCurrencyIds, $dryRun, $templateId, $startPlaceId, $foreignCurrencies) {
        if (empty($entries)) {
            return;
        }

        $qtyId = (int)($entries[0]->event_template_qty_id ?? 0);

        $plnRow = null;
        foreach ($entries as $candidate) {
            if (in_array($candidate->currency_id, $plnCurrencyIds, true)) {
                $plnRow = $candidate;
                break;
            }
        }

        if (!$plnRow) {
            return;
        }

        $plnPrice = $plnRow->price_per_person ?? null;
        if ($plnPrice === null || (float)$plnPrice <= 0) {
            return;
        }

        $plnUpdated = $plnRow->updated_at ? Carbon::parse($plnRow->updated_at) : null;

        $currencyIds = array_unique(array_map(static fn($row) => (int)$row->currency_id, $entries));
        $currencyCache = Currency::whereIn('id', $currencyIds)->get()->keyBy('id');
        $existingByCurrencyId = collect($entries)->keyBy(fn($row) => (int)$row->currency_id);

        foreach ($entries as $row) {
            if (in_array($row->currency_id, $plnCurrencyIds, true)) {
                continue;
            }

            $currency = $currencyCache->get($row->currency_id);
            if (!$currency) {
                echo "[sync] Pomijam – brak waluty ID {$row->currency_id}\n";
                continue;
            }

            $exchangeRate = (float)$currency->exchange_rate;
            if ($exchangeRate <= 0) {
                echo "[sync] Pomijam – kurs <= 0 dla waluty {$currency->symbol}\n";
                continue;
            }

            $needsResync = true;
            if ($plnUpdated && $row->updated_at) {
                $foreignUpdated = Carbon::parse($row->updated_at);
                $needsResync = !$foreignUpdated->equalTo($plnUpdated);
            }

            if (!$needsResync && (float)$row->price_per_person > 0) {
                continue;
            }

            $converted = (float)$plnPrice / $exchangeRate;
            $currencyCode = $currency->symbol ?: ($currency->code ?? '');
            $rounded = PriceRoundingService::roundPerPerson($converted, $currencyCode);

            if ($rounded === null) {
                $rounded = round($converted, 2);
            }

            if ($rounded <= 0) {
                echo "[sync] Usuwam rekord waluty {$currencyCode} (id={$row->id}) – cena po przeliczeniu = 0\n";
                if (!$dryRun) {
                    DB::table('event_template_price_per_person')->where('id', $row->id)->delete();
                }
                continue;
            }

            $updateData = [
                'price_per_person' => $rounded,
                'tax_amount' => 0,
            ];

            $updateData['updated_at'] = $plnUpdated ? $plnUpdated : Carbon::now();

            $fieldMap = ['price_base', 'markup_amount', 'price_with_tax', 'transport_cost'];
            foreach ($fieldMap as $field) {
                $plnValue = $plnRow->{$field} ?? null;
                if ($plnValue !== null) {
                    $updateData[$field] = round(((float)$plnValue) / $exchangeRate, 2);
                }
            }

            $updateData['tax_breakdown'] = json_encode([]);

            echo "[sync] Przeliczam walutę {$currencyCode} (qty={$qtyId}) na {$rounded}\n";
            if (!$dryRun) {
                DB::table('event_template_price_per_person')->where('id', $row->id)->update($updateData);
            }
        }

        // Create missing foreign currency rows based on current PLN price
        foreach ($foreignCurrencies as $currencyId => $currency) {
            if (in_array($currencyId, $plnCurrencyIds, true)) {
                continue;
            }

            if ($existingByCurrencyId->has($currencyId)) {
                continue;
            }

            $exchangeRate = (float) $currency->exchange_rate;
            if ($exchangeRate <= 0) {
                echo "[sync] Pomijam tworzenie waluty {$currency->symbol} – kurs <= 0\n";
                continue;
            }

            $currencyCode = $currency->symbol ?: ($currency->code ?? '');
            $converted = (float) $plnPrice / $exchangeRate;
            $rounded = PriceRoundingService::roundPerPerson($converted, $currencyCode ?: $currency->name ?? '');

            if ($rounded === null || $rounded <= 0) {
                echo "[sync] Pomijam tworzenie waluty {$currencyCode} – wynik <= 0\n";
                continue;
            }

            $insertData = [
                'event_template_id' => $templateId,
                'event_template_qty_id' => $qtyId,
                'currency_id' => $currencyId,
                'start_place_id' => $startPlaceId,
                'price_per_person' => $rounded,
                'price_base' => $plnRow->price_base !== null ? round(((float)$plnRow->price_base) / $exchangeRate, 2) : null,
                'markup_amount' => $plnRow->markup_amount !== null ? round(((float)$plnRow->markup_amount) / $exchangeRate, 2) : null,
                'tax_amount' => 0,
                'transport_cost' => $plnRow->transport_cost !== null ? round(((float)$plnRow->transport_cost) / $exchangeRate, 2) : null,
                'price_with_tax' => $plnRow->price_with_tax !== null ? round(((float)$plnRow->price_with_tax) / $exchangeRate, 2) : null,
                'tax_breakdown' => json_encode([]),
                'created_at' => Carbon::now(),
                'updated_at' => $plnUpdated ? $plnUpdated : Carbon::now(),
            ];

            if ($dryRun) {
                echo "[sync] (dry) Tworzyłbym walutę {$currencyCode} (qty={$qtyId}) = {$rounded}\n";
            } else {
                DB::table('event_template_price_per_person')->insert($insertData);
                echo "[sync] Utworzono walutę {$currencyCode} (qty={$qtyId}) = {$rounded}\n";
            }
        }
    };

    $currentQtyId = null;
    $currentGroup = [];
    foreach ($query->orderBy('event_template_qty_id')->orderBy('currency_id')->cursor() as $row) {
        $rowQtyId = (int)$row->event_template_qty_id;
        if ($currentQtyId !== null && $rowQtyId !== $currentQtyId) {
            $processGroup($currentGroup);
            $currentGroup = [];
        }
        $currentGroup[] = $row;
        $currentQtyId = $rowQtyId;
    }

    if (!empty($currentGroup)) {
        $processGroup($currentGroup);
    }
}

/**
 * Usuwa historyczne wpisy cenowe w walutach innych niż PLN dla wskazanej kombinacji szablonu i miejsca startowego.
 * Dzięki temu kolejne uruchomienia skryptu nie pozostawiają starych wartości w obcych walutach.
 */
function removeForeignCurrencyRows(int $templateId, ?int $startPlaceId, bool $dryRun = false): void
{
    $plnCurrencyIds = Currency::plnIds();
    if (empty($plnCurrencyIds)) {
        echo "[purge] Pomijam – brak zdefiniowanej waluty PLN\n";
        return;
    }

    $query = DB::table('event_template_price_per_person')
        ->where('event_template_id', $templateId);

    if ($startPlaceId === null) {
        $query->whereNull('start_place_id');
    } else {
        $query->where('start_place_id', $startPlaceId);
    }

    $ids = $query->whereNotIn('currency_id', $plnCurrencyIds)->pluck('id')->all();

    if (empty($ids)) {
        return;
    }

    $count = count($ids);
    $context = "template={$templateId}, start_place=" . ($startPlaceId ?? 'null');
    echo "[purge] " . ($dryRun ? "Symuluję usunięcie" : "Usuwam") . " {$count} rekordów walut obcych ({$context})\n";

    if (!$dryRun) {
        DB::table('event_template_price_per_person')->whereIn('id', $ids)->delete();
    }
}

// Rozszerzenie: pełne kombinacje (template x miejsca startowe) + availability
$allPlaces = \App\Models\Place::where('starting_place', true)->pluck('id')->toArray();
$allTemplates = \App\Models\EventTemplate::when($only, fn($q) => $q->where('id', $only))->pluck('id')->toArray();

$availabilityPairs = DB::table('event_template_starting_place_availability')
    ->whereNotNull('start_place_id')
    ->when($only, fn($q) => $q->where('event_template_id', $only))
    ->select('event_template_id', 'start_place_id')
    ->distinct()
    ->get()
    ->map(fn($r) => ['event_template_id' => (int)$r->event_template_id, 'start_place_id' => (int)$r->start_place_id])
    ->toArray();

$fullPairs = [];
foreach ($allTemplates as $tid) {
    foreach ($allPlaces as $pid) {
        $fullPairs[] = ['event_template_id' => (int)$tid, 'start_place_id' => (int)$pid];
    }
}

// scal i usuń duplikaty
$pairs = collect(array_merge($availabilityPairs, $fullPairs))
    ->unique(fn($p) => $p['event_template_id'] . '-' . $p['start_place_id'])
    ->values();

if ($limit) {
    $pairs = $pairs->slice(0, $limit);
}

$report = [];
$problematic = [];
$totalSaved = 0;

foreach ($pairs as $p) {
    $templateId = (int)$p['event_template_id'];
    $startPlaceId = (int)$p['start_place_id'];
    echo "Processing template={$templateId}, start_place={$startPlaceId}\n";

    $template = EventTemplate::withTrashed()->find($templateId);
    if (!$template) {
        $problematic[] = [
            'template_id' => $templateId,
            'start_place_id' => $startPlaceId,
            'reason' => 'template_not_found'
        ];
        continue;
    }

    $allowForeignCurrencies = method_exists($template, 'isForeignTrip') ? $template->isForeignTrip() : true;

    if ($legacyMode) {
        $calc = $engine->calculateDetailed($template, $startPlaceId);
        if (empty($calc)) {
            $problematic[] = [
                'template_id' => $templateId,
                'start_place_id' => $startPlaceId,
                'reason' => 'no_calc_variants'
            ];
            continue;
        }
    } else {
        // Unified persist automatycznie zapisze – w trybie dry run pobierz tylko calculate()
        $uData = $unified->calculate($template, $startPlaceId, false);
        if (empty($uData)) {
            $problematic[] = [
                'template_id' => $templateId,
                'start_place_id' => $startPlaceId,
                'reason' => 'no_calc_variants_unified'
            ];
            continue;
        }
        if (!$dryRun) {
            if ($deleteExisting) {
                \App\Models\EventTemplatePricePerPerson::where('event_template_id', $templateId)
                    ->where('start_place_id', $startPlaceId)
                    ->delete();
            } else {
                removeForeignCurrencyRows($templateId, $startPlaceId, $dryRun);
            }
            if ($deleteExisting) {
                // wszystkie wpisy zostały już usunięte powyżej
            }
            // IMPORTANT: pass through deleteExisting so stale currencies are removed when a template switches currency set
            $unified->calculateAndPersist($template, $startPlaceId, $deleteExisting);
            $totalSaved++; // przyjmujemy minimum 1 – dokładne zliczanie wymagałoby iteracji ilości walut * qty
        } else {
            echo "(dry) Would persist unified qty variants: " . count($uData) . "\n";
        }
        // w trybie unified pomijamy dalszą część legacy zapisu
        continue;
    }

    // Existing rows count for info (we will upsert instead of delete+insert)
    $oldCount = DB::table('event_template_price_per_person')
        ->where('event_template_id', $templateId)
        ->where('start_place_id', $startPlaceId)
        ->count();

    echo ($dryRun ? "Would upsert (keep existing) $oldCount existing rows\n" : "Will upsert (keep existing) $oldCount existing rows\n");

    // If configured to delete existing, perform purge once before inserting new calculated rows
    if ($deleteExisting) {
        echo ($dryRun ? "(dry) Would delete existing price rows for template={$templateId}, start_place={$startPlaceId}\n" : "Deleting existing price rows for template={$templateId}, start_place={$startPlaceId}\n");
        if (!$dryRun) {
            DB::table('event_template_price_per_person')
                ->where('event_template_id', $templateId)
                ->where('start_place_id', $startPlaceId)
                ->delete();
            // reset oldCount for accurate messaging
            $oldCount = 0;
        }
    }

    // LEGACY BLOK: dla kazdego wariantu qty w calc, zapisac wiersze (mozliwe rozne waluty)
    foreach ($calc as $qty => $data) {
        // znalezienie odpowiadajacego event_template_qty_id
        $qtyModel = null;
        $qtyLookupMethod = null;
        if (!empty($data['event_template_qty_id'])) {
            $qtyModel = EventTemplateQty::find((int)$data['event_template_qty_id']);
            $qtyLookupMethod = 'by_id_from_engine';
        }
        // fallback: engine keys are plain qty numbers (20,25...) - spróbuj znaleźć po wartości qty
        if (!$qtyModel) {
            $qtyModel = EventTemplateQty::where('event_template_id', $templateId)->where('qty', $qty)->first();
            $qtyLookupMethod = $qtyModel ? 'by_qty_value' : 'not_found';
        }

        if (!$qtyModel) {
            $problematic[] = [
                'template_id' => $templateId,
                'start_place_id' => $startPlaceId,
                'qty' => $qty,
                'reason' => 'qty_not_found',
                'lookup' => $qtyLookupMethod
            ];
            continue;
        }

        // data moze zawierac 'currencies' - jesli tak, zapisz dla kazdej waluty
        if (!empty($data['currencies']) && is_array($data['currencies'])) {
            foreach ($data['currencies'] as $currencyCode => $cvals) {
                if ($currencyCode !== 'PLN' && !$allowForeignCurrencies) {
                    continue;
                }
                // znajdz currency_id
                $currency = Currency::where('symbol', $currencyCode)->orWhere('name', $currencyCode)->first();
                if (!$currency) {
                    $problematic[] = [
                        'template_id' => $templateId,
                        'start_place_id' => $startPlaceId,
                        'qty' => $qty,
                        'currency' => $currencyCode,
                        'reason' => 'currency_not_found'
                    ];
                    continue;
                }
                // zastosuj wspólną regułę zaokrąglania
                $pp = $cvals['price_per_person'] ?? null;
                if ($pp !== null) {
                    $pp = PriceRoundingService::roundPerPerson((float)$pp, $currency->symbol ?: ($currency->code ?? ''));
                }

                $row = [
                    'event_template_id' => $templateId,
                    'event_template_qty_id' => $qtyModel->id,
                    'currency_id' => $currency->id,
                    'start_place_id' => $startPlaceId,
                    'price_per_person' => $pp,
                    'price_base' => $cvals['price_base'] ?? null,
                    'markup_amount' => $cvals['markup_amount'] ?? null,
                    'tax_amount' => $cvals['tax_amount'] ?? null,
                    'transport_cost' => $cvals['transport_cost'] ?? ($data['transport_cost'] ?? null),
                    // price_with_tax: dla walut obcych brak podatków w silniku – użyj total_with_markup_and_tax; dla PLN analogicznie
                    'price_with_tax' => $cvals['total_with_markup_and_tax'] ?? ($data['price_with_tax'] ?? null),
                    'tax_breakdown' => json_encode($cvals['tax_breakdown'] ?? ($data['tax_breakdown'] ?? [])),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ];

                // ensure mandatory ids present
                $missing = [];
                foreach (['event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id'] as $k) {
                    if (empty($row[$k]) && $row[$k] !== 0) $missing[] = $k;
                }
                if (!empty($missing)) {
                    $problematic[] = array_merge($row, ['reason' => 'missing_ids:' . implode(',', $missing)]);
                    continue;
                }

                            if (!$dryRun) {
                                // manual upsert: update if exists else insert (avoid requiring DB unique constraint)
                                $exists = DB::table('event_template_price_per_person')
                                    ->where('event_template_id', $row['event_template_id'])
                                    ->where('event_template_qty_id', $row['event_template_qty_id'])
                                    ->where('currency_id', $row['currency_id'])
                                    ->where('start_place_id', $row['start_place_id'])
                                    ->first();
                                if ($exists) {
                                    DB::table('event_template_price_per_person')
                                        ->where('id', $exists->id)
                                        ->update(array_merge($row, ['updated_at' => Carbon::now()]));
                                } else {
                                    DB::table('event_template_price_per_person')->insert($row);
                                }
                                $totalSaved++;
                            }
            }
        } else {
            // brak rozbicia po walutach: zapisz jako domyslna waluta PLN (lub warn)
            $pln = Currency::where('symbol', 'PLN')->orWhere('name', 'Polski złoty')->first();
            if (!$pln) {
                $problematic[] = [
                    'template_id' => $templateId,
                    'start_place_id' => $startPlaceId,
                    'qty' => $qty,
                    'reason' => 'pln_currency_missing'
                ];
                continue;
            }
            // zaokrąglij (PLN reguła 5)
            $pp = $data['price_per_person'] ?? null;
            if ($pp !== null) {
                $pp = PriceRoundingService::roundPerPerson((float)$pp, 'PLN');
            }
            $row = [
                'event_template_id' => $templateId,
                'event_template_qty_id' => $qtyModel->id,
                'currency_id' => $pln->id,
                'start_place_id' => $startPlaceId,
                'price_per_person' => $pp,
                'price_base' => $data['price_base'] ?? null,
                'markup_amount' => $data['markup_amount'] ?? null,
                'tax_amount' => $data['tax_amount'] ?? null,
                'transport_cost' => $data['transport_cost'] ?? null,
                'price_with_tax' => $data['price_with_tax'] ?? null,
                'tax_breakdown' => json_encode($data['tax_breakdown'] ?? []),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
            $missing = [];
            foreach (['event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id'] as $k) {
                if (empty($row[$k]) && $row[$k] !== 0) $missing[] = $k;
            }
            if (!empty($missing)) {
                $problematic[] = array_merge($row, ['reason' => 'missing_ids:' . implode(',', $missing)]);
                continue;
            }
            if (!$dryRun) {
                $exists = DB::table('event_template_price_per_person')
                    ->where('event_template_id', $row['event_template_id'])
                    ->where('event_template_qty_id', $row['event_template_qty_id'])
                    ->where('currency_id', $row['currency_id'])
                    ->where('start_place_id', $row['start_place_id'])
                    ->first();
                if ($exists) {
                    DB::table('event_template_price_per_person')
                        ->where('id', $exists->id)
                        ->update(array_merge($row, ['updated_at' => Carbon::now()]));
                } else {
                    DB::table('event_template_price_per_person')->insert($row);
                }
                $totalSaved++;
            }
        }
    }

    if ($allowForeignCurrencies) {
        try {
            resyncForeignCurrencyRows($templateId, $startPlaceId, $dryRun, $allowForeignCurrencies);
        } catch (\Throwable $e) {
            echo "[sync][error] " . $e->getMessage() . "\n";
        }
    }
}

// zapisz raporty
$reportPath = __DIR__ . '/rewrite_prices_report_' . date('Ymd_His') . '.json';
echo "[report] Zapis raportu do {$reportPath}\n";
file_put_contents($reportPath, json_encode(['summary' => ['pairs' => count($pairs), 'saved' => $totalSaved], 'problematic' => $problematic], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Done. Report: $reportPath\n";
if (!empty($problematic)) {
    echo "Problematic combinations found: " . count($problematic) . " - see report.\n";
}

return 0;
