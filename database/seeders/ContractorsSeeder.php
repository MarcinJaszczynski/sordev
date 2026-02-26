<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContractorsSeeder extends Seeder
{
    public function run(): void
    {
        $dumpPath = base_path('sorstary/host803729_bazasor.sql');
        $contractors = [];

        // helper: split a SQL value tuple into components while respecting quoted strings
        $splitRow = function (string $row): array {
            $len = strlen($row);
            $parts = [];
            $buf = '';
            $inQuote = false;
            $quoteChar = '';
            for ($i = 0; $i < $len; $i++) {
                $ch = $row[$i];
                if (!$inQuote) {
                    if ($ch === "'" || $ch === '"') {
                        $inQuote = true;
                        $quoteChar = $ch;
                        $buf .= $ch;
                        continue;
                    }
                    if ($ch === ',') {
                        $parts[] = trim($buf);
                        $buf = '';
                        continue;
                    }
                    $buf .= $ch;
                } else {
                    $buf .= $ch;
                    if ($ch === $quoteChar) {
                        // handle doubled quote (SQL escaping)
                        if ($i + 1 < $len && $row[$i + 1] === $quoteChar) {
                            $buf .= $row[$i + 1];
                            $i++;
                            continue;
                        }
                        $inQuote = false;
                        $quoteChar = '';
                    } elseif ($ch === '\\' && $i + 1 < $len) {
                        // backslash escape: include next char and skip it in loop
                        $buf .= $row[$i + 1];
                        $i++;
                    }
                }
            }
            $parts[] = trim($buf);
            return $parts;
        };

        $parseValue = function ($v) {
            $v = trim($v);
            if (strcasecmp($v, 'NULL') === 0) {
                return null;
            }
            if ($v === '') {
                return null;
            }
            $first = $v[0] ?? '';
            $last = substr($v, -1);
            if (($first === "'" && $last === "'") || ($first === '"' && $last === '"')) {
                $v = substr($v, 1, -1);
            }
            // unescape doubled single quotes and backslash escapes
            $v = str_replace("''", "'", $v);
            $v = str_replace(['\\\\', "\\'", '\\"'], ['\\', "'", '"'], $v);
            return $v;
        };

        if (file_exists($dumpPath) && is_readable($dumpPath)) {
            $content = file_get_contents($dumpPath);
            if (preg_match_all('/INSERT INTO `contractors`\s*\(([^)]+)\)\s*VALUES\s*(.*?);/is', $content, $inserts, PREG_SET_ORDER)) {
                foreach ($inserts as $insert) {
                    $colsRaw = $insert[1];
                    $cols = array_map(function ($c) {
                        return trim(trim($c), "` ");
                    }, explode(',', $colsRaw));

                    $valuesBlock = $insert[2];
                    if (preg_match_all('/\(([^)]*)\)/', $valuesBlock, $rows)) {
                        foreach ($rows[1] as $row) {
                            $parts = $splitRow($row);
                            if (count($parts) !== count($cols)) {
                                // skip malformed row
                                continue;
                            }
                            $assoc = [];
                            foreach ($cols as $i => $col) {
                                $assoc[$col] = $parseValue($parts[$i]);
                            }
                            $contractors[] = $assoc;
                        }
                    }
                }
            }
        }

        // fallback sample if parsing failed or dump missing
        if (empty($contractors)) {
            $contractors = [
                ['id'=>1,'created_at'=>'2023-02-22 13:17:32','updated_at'=>'2023-02-23 13:47:35','name'=>'Tomek Chrusciel','street'=>null,'city'=>null,'region'=>null,'country'=>null,'nip'=>null,'phone'=>'603 846 062','email'=>null,'www'=>null,'description'=>null,'firstname'=>'Tomek','surname'=>'Chrusciel'],
                ['id'=>2,'created_at'=>'2023-02-24 12:25:21','updated_at'=>'2023-02-24 12:25:21','name'=>'Tolek Tour','street'=>'42 lokal 501 Nowogrodzka','city'=>'Warszawa','region'=>null,'country'=>null,'nip'=>null,'phone'=>'656576578','email'=>null,'www'=>null,'description'=>null,'firstname'=>null,'surname'=>null],
            ];
        }

        // Insert in chunks to avoid huge single queries. Use insertOrIgnore to be idempotent.
        foreach (array_chunk($contractors, 200) as $chunk) {
            DB::table('contractors')->insertOrIgnore($chunk);
        }
    }
}
