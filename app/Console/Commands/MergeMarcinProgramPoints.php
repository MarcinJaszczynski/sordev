<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeMarcinProgramPoints extends Command
{
    protected $signature = 'db:merge-marcin-program-points {--path=}';
    protected $description = 'Dodaj brakujące rekordy z database_marcin.sqlite:event_template_program_points na końcu tabeli w database.sqlite bez duplikatów (porównanie po treści)';

    public function handle(): int
    {
        $defaultPath = database_path('database_marcin.sqlite');
        $path = $this->option('path') ?: $defaultPath;
        if (!file_exists($path)) {
            $this->error("Nie znaleziono pliku: {$path}");
            return 1;
        }

        $driver = config('database.default');
        $conn = config("database.connections.{$driver}");
        if (($conn['driver'] ?? '') !== 'sqlite') {
            $this->error('Ta komenda działa tylko na SQLite.');
            return 1;
        }

        // Kopia zapasowa
        $mainPath = database_path('database.sqlite');
        if (file_exists($mainPath)) {
            $stamp = date('Ymd_His');
            @copy($mainPath, database_path("database_backup_{$stamp}.sqlite"));
        }

        DB::statement("ATTACH DATABASE '" . str_replace("'", "''", $path) . "' AS marcin");

        try {
            // Kolumny w obu tabelach
            $srcCols = collect(DB::select("PRAGMA marcin.table_info('event_template_program_points')"));
            $dstCols = collect(DB::select("PRAGMA table_info('event_template_program_points')"));
            if ($srcCols->isEmpty() || $dstCols->isEmpty()) {
                $this->error('Tabela event_template_program_points nie istnieje w jednej z baz.');
                DB::statement('DETACH DATABASE marcin');
                return 1;
            }

            $srcNames = $srcCols->pluck('name')->all();
            $dstNames = $dstCols->pluck('name')->all();
            $common = array_values(array_intersect($srcNames, $dstNames));

            // Kolumny do porównania (bez id, created_at, updated_at)
            $compare = array_values(array_diff($common, ['id', 'created_at', 'updated_at']));
            if (empty($compare)) {
                $this->warn('Brak wspólnych kolumn do porównania treści.');
            }

            // Kolumny do wstawienia (bez id; jeżeli timestamps istnieją, też je kopiujemy)
            $insert = array_values(array_diff($common, ['id']));
            $colsList = implode(', ', array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', $insert));
            $colsSelect = implode(', ', array_map(fn($c) => 's."' . str_replace('"', '""', $c) . '"', $insert));

            // Warunek duplikatu po treści
            $eqConds = [];
            foreach ($compare as $c) {
                $q = '"' . str_replace('"', '""', $c) . '"';
                $eqConds[] = "(m.$q = s.$q OR (m.$q IS NULL AND s.$q IS NULL))";
            }
            $dupWhere = empty($eqConds) ? '0' : implode(' AND ', $eqConds);

            // FK warunki bezpieczeństwa
            $fkConds = [];
            if (in_array('currency_id', $insert, true)) {
                $fkConds[] = ' (s."currency_id" IS NULL OR EXISTS (SELECT 1 FROM "currencies" c WHERE c."id" = s."currency_id")) ';
            }
            if (in_array('parent_id', $insert, true)) {
                $fkConds[] = ' (s."parent_id" IS NULL OR EXISTS (SELECT 1 FROM "event_template_program_points" p WHERE p."id" = s."parent_id")) ';
            }
            $fkWhere = empty($fkConds) ? '1' : implode(' AND ', $fkConds);

            DB::beginTransaction();
            DB::statement('PRAGMA foreign_keys=ON');

            // Wstaw tylko te rekordy, które nie mają odpowiednika po treści w bazie docelowej.
            $sql = "INSERT INTO \"event_template_program_points\" ($colsList)\n"
                 . "SELECT $colsSelect\n"
                 . "FROM marcin.\"event_template_program_points\" s\n"
                 . "WHERE NOT EXISTS (\n"
                 . "  SELECT 1 FROM \"event_template_program_points\" m\n"
                 . "  WHERE $dupWhere\n"
                 . ")\n"
                 . "AND $fkWhere";

            $before = DB::table('event_template_program_points')->count();
            DB::statement($sql);
            $after = DB::table('event_template_program_points')->count();
            $inserted = max($after - $before, 0);

            DB::commit();

            $this->info('Dodano rekordów: ' . $inserted);
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Błąd: ' . $e->getMessage());
            DB::statement('DETACH DATABASE marcin');
            return 1;
        }

        DB::statement('DETACH DATABASE marcin');
        return 0;
    }
}
