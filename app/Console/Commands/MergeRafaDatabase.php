<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeRafaDatabase extends Command
{
    protected $signature = 'db:merge-rafa {--path=}';
    protected $description = 'Uzupełnij database.sqlite brakującymi danymi z database_rafa.sqlite bez zmiany struktury i kluczy';

    public function handle(): int
    {
        $defaultPath = database_path('database_rafa.sqlite');
        $path = $this->option('path') ?: $defaultPath;
        if (!file_exists($path)) {
            $this->error("Nie znaleziono pliku: {$path}");
            return 1;
        }

        // Upewnij się, że używamy SQLite
        $driver = config('database.default');
        $conn = config("database.connections.{$driver}");
        if (($conn['driver'] ?? '') !== 'sqlite') {
            $this->error('Ta komenda działa tylko na SQLite (aktualne połączenie nie jest sqlite).');
            return 1;
        }

        // Kopia zapasowa głównej bazy
        $mainPath = database_path('database.sqlite');
        if (file_exists($mainPath)) {
            $stamp = date('Ymd_His');
            $backup = database_path("database_backup_{$stamp}.sqlite");
            if (@copy($mainPath, $backup)) {
                $this->info("Utworzono kopię zapasową: {$backup}");
            } else {
                $this->warn('Nie udało się utworzyć kopii zapasowej (kontynuuję)');
            }
        }

        $this->info('ATTACH rafa: ' . $path);
        DB::statement("ATTACH DATABASE '" . str_replace("'", "''", $path) . "' AS rafa");

        // Pobierz listę tabel w source i target
        $srcTables = collect(DB::select("SELECT name FROM rafa.sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name')->values()->all();
        $dstTables = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name')->values()->all();

        $commonTables = array_values(array_intersect($srcTables, $dstTables));
        if (empty($commonTables)) {
            $this->warn('Brak wspólnych tabel do zmergowania.');
            DB::statement('DETACH DATABASE rafa');
            return 0;
        }

        // Zbuduj graf zależności na podstawie kluczy obcych w docelowej bazie (main)
        $deps = [];
        foreach ($commonTables as $t) {
            $deps[$t] = [];
            $fkList = DB::select("PRAGMA foreign_key_list('" . str_replace("'", "''", $t) . "')");
            foreach ($fkList as $fk) {
                $parent = $fk->table ?? null;
                if ($parent && in_array($parent, $commonTables, true)) {
                    // t zależy od parent -> parent musi być wcześniej
                    $deps[$t][] = $parent;
                }
            }
        }

        // Topologiczne sortowanie (Kahn)
        $order = $this->topoSort($commonTables, $deps);
        if (empty($order)) {
            $this->warn('Nie udało się ustalić kolejności zależności — używam listy domyślnej.');
            $order = $commonTables;
        }

        $insertedTotal = 0;
        $perTable = [];

        DB::beginTransaction();
        try {
            DB::statement('PRAGMA foreign_keys=ON');
            foreach ($order as $table) {
                // Wspólne kolumny
                $srcCols = collect(DB::select("PRAGMA rafa.table_info('" . str_replace("'", "''", $table) . "')"));
                $dstCols = collect(DB::select("PRAGMA table_info('" . str_replace("'", "''", $table) . "')"));
                $srcColNames = $srcCols->pluck('name')->all();
                $dstColNames = $dstCols->pluck('name')->all();
                $commonCols = array_values(array_intersect($srcColNames, $dstColNames));
                if (empty($commonCols)) {
                    continue; // nic wspólnego do skopiowania
                }

                // PK kolumny
                $pkCols = $dstCols->filter(fn($c) => ($c->pk ?? 0) > 0)->sortBy('pk')->pluck('name')->values()->all();

                // Zbuduj SQL
                $colsList = implode(', ', array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', $commonCols));
                $colsSelect = implode(', ', array_map(fn($c) => 's."' . str_replace('"', '""', $c) . '"', $commonCols));

                // Warunki istnienia rekordów nadrzędnych (FK) w bazie docelowej
                $fkDefs = collect(DB::select("PRAGMA foreign_key_list('" . str_replace("'", "''", $table) . "')"));
                $fkExistsConds = [];
                if ($fkDefs->count() > 0) {
                    // grupuj po id (pojedyncza definicja FK może mieć wiele kolumn)
                    $byId = $fkDefs->groupBy('id');
                    foreach ($byId as $id => $group) {
                        $parentTable = $group->first()->table ?? null;
                        if (!$parentTable) { continue; }
                        // pomiń jeśli parent nie jest w docelowej bazie (teoretycznie nie powinno się zdarzyć)
                        try {
                            // sprawdź czy tabela istnieje w main
                            $exists = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$parentTable]))->isNotEmpty();
                        } catch (\Throwable $e) { $exists = false; }
                        if (!$exists) { continue; }
                        $pairs = [];
                        foreach ($group as $g) {
                            $from = $g->from; // kolumna w $table
                            $to = $g->to;     // kolumna w $parentTable
                            if (!in_array($from, $commonCols, true)) { $pairs = []; break; }
                            $pairs[] = 'p."' . str_replace('"', '""', $to) . '" = s."' . str_replace('"', '""', $from) . '"';
                        }
                        if (!empty($pairs)) {
                            $fkExistsConds[] = 'EXISTS (SELECT 1 FROM "' . str_replace('"', '""', $parentTable) . '" p WHERE ' . implode(' AND ', $pairs) . ')';
                        }
                    }
                }

                if (!empty($pkCols)) {
                    // Wstaw tylko rekordy, których PK nie ma w main
                    $joinConds = [];
                    foreach ($pkCols as $pk) {
                        if (!in_array($pk, $commonCols, true)) { continue 2; } // jeśli PK nie we wspólnych kolumnach, pomiń tę tabelę
                        $joinConds[] = 'm."' . str_replace('"', '""', $pk) . '" = s."' . str_replace('"', '""', $pk) . '"';
                    }
                    $join = implode(' AND ', $joinConds);
                    $where = implode(' AND ', array_map(fn($pk)=>"m.\"$pk\" IS NULL", $pkCols));
                    if (!empty($fkExistsConds)) {
                        $where = '(' . $where . ') AND ' . implode(' AND ', $fkExistsConds);
                    }
                    $sql = "INSERT INTO \"$table\" ($colsList)\n"
                         . "SELECT $colsSelect FROM rafa.\"$table\" s\n"
                         . "LEFT JOIN \"$table\" m ON $join\n"
                         . "WHERE $where";
                } else {
                    // Brak PK — próbuj bezpiecznie z INSERT OR IGNORE (zda się na istniejące unikalne indeksy)
                    $sql = "INSERT OR IGNORE INTO \"$table\" ($colsList)\nSELECT $colsSelect FROM rafa.\"$table\" s";
                    if (!empty($fkExistsConds)) {
                        $sql .= "\nWHERE " . implode(' AND ', $fkExistsConds);
                    }
                }

                $before = DB::table($table)->count();
                DB::statement($sql);
                $after = DB::table($table)->count();
                $inserted = max($after - $before, 0);
                $insertedTotal += $inserted;
                $perTable[$table] = $inserted;
                $this->line(sprintf('Tabela %-45s +%d', $table, $inserted));
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Błąd podczas merge: ' . $e->getMessage());
            DB::statement('DETACH DATABASE rafa');
            return 1;
        }

        DB::statement('DETACH DATABASE rafa');

        $this->info('Zakończono. Łącznie dodano: ' . $insertedTotal . ' rekordów.');
        foreach ($perTable as $t => $n) {
            if ($n > 0) $this->line("  - {$t}: {$n}");
        }
        return 0;
    }

    private function topoSort(array $nodes, array $deps): array
    {
        // policz stopnie wejściowe
        $in = array_fill_keys($nodes, 0);
        foreach ($deps as $n => $parents) {
            foreach ($parents as $p) { if (isset($in[$n])) $in[$n]++; }
        }
        $queue = [];
        foreach ($in as $n => $deg) { if ($deg === 0) $queue[] = $n; }
        $order = [];
        while (!empty($queue)) {
            $n = array_shift($queue);
            $order[] = $n;
            // zmniejsz stopnie dzieci (kto zależy od n)
            foreach ($deps as $child => $parents) {
                if (in_array($n, $parents, true)) {
                    $in[$child]--;
                    if ($in[$child] === 0) $queue[] = $child;
                }
            }
        }
        // jeśli nie pokryliśmy wszystkich, zwróć tylko to co mamy
        return $order;
    }
}
