<?php

namespace App\Services\Legacy;

/**
 * Lekki parser zrzutów phpMyAdmin (INSERT INTO `table` (...) VALUES (...),(...);).
 */
class OldSorSqlDumpReader
{
    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    public function readTable(string $sql, string $table): array
    {
        $columns = [];
        $rows = [];

        if (! preg_match_all(
            '/INSERT INTO `'.preg_quote($table, '/').'`\s*\(([^)]+)\)\s*VALUES\s*/i',
            $sql,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            return ['columns' => [], 'rows' => []];
        }

        foreach ($matches[0] as $i => $match) {
            if ($columns === []) {
                $columns = array_map(
                    static fn (string $col): string => trim($col, " `\n\r\t"),
                    explode(',', $matches[1][$i][0])
                );
            }

            $start = $match[1] + strlen($match[0]);
            $end = strpos($sql, ";\n", $start);
            if ($end === false) {
                $end = strpos($sql, ';', $start);
            }
            if ($end === false) {
                continue;
            }

            foreach ($this->parseTuples(substr($sql, $start, $end - $start)) as $tuple) {
                $assoc = [];
                foreach ($columns as $ci => $col) {
                    $assoc[$col] = $this->unquote($tuple[$ci] ?? null);
                }
                $rows[] = $assoc;
            }
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * @return list<list<string>>
     */
    private function parseTuples(string $vals): array
    {
        $depth = 0;
        $inStr = false;
        $strCh = '';
        $esc = false;
        $cur = [];
        $field = [];
        $rows = [];
        $len = strlen($vals);

        for ($i = 0; $i < $len; $i++) {
            $ch = $vals[$i];

            if ($inStr) {
                $field[] = $ch;
                if ($esc) {
                    $esc = false;
                } elseif ($ch === '\\') {
                    $esc = true;
                } elseif ($ch === $strCh) {
                    $inStr = false;
                }

                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $inStr = true;
                $strCh = $ch;
                $field[] = $ch;

                continue;
            }

            if ($ch === '(') {
                if ($depth === 0) {
                    $cur = [];
                    $field = [];
                }
                $depth++;
                if ($depth > 1) {
                    $field[] = $ch;
                }

                continue;
            }

            if ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    $cur[] = trim(implode('', $field));
                    $rows[] = $cur;
                } elseif ($depth >= 1) {
                    $field[] = $ch;
                }

                continue;
            }

            if ($ch === ',' && $depth === 1) {
                $cur[] = trim(implode('', $field));
                $field = [];

                continue;
            }

            if ($depth >= 1) {
                $field[] = $ch;
            }
        }

        return $rows;
    }

    private function unquote(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;
        if (strtoupper($value) === 'NULL') {
            return null;
        }

        if (strlen($value) >= 2 && $value[0] === "'" && str_ends_with($value, "'")) {
            return stripcslashes(substr($value, 1, -1));
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }
}
