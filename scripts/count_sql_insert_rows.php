<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/count_sql_insert_rows.php <sql_file> <table>\n");
    exit(2);
}

$file = $argv[1];
$table = $argv[2];

$fh = fopen($file, 'rb');
if ($fh === false) {
    fwrite(STDERR, "Cannot open {$file}\n");
    exit(3);
}

$buffer = '';
$stmts = 0;
$rows = 0;
$maxId = 0;

while (! feof($fh)) {
    $buffer .= fread($fh, 1024 * 1024) ?: '';
    while (($pos = strpos($buffer, 'INSERT')) !== false) {
        $semi = strpos($buffer, ';', $pos);
        if ($semi === false) {
            break;
        }
        $stmt = substr($buffer, $pos, $semi - $pos + 1);
        $buffer = substr($buffer, $semi + 1);

        if (! preg_match('/INSERT(?: IGNORE)? INTO `'.preg_quote($table, '/').'`/i', $stmt)) {
            continue;
        }

        $stmts++;
        if (! preg_match('/VALUES\\s*(.+);/s', $stmt, $m)) {
            continue;
        }

        $vals = $m[1];
        $inString = false;
        $escape = false;
        $depth = 0;
        for ($i = 0, $len = strlen($vals); $i < $len; $i++) {
            $ch = $vals[$i];
            if ($inString) {
                if ($escape) {
                    $escape = false;
                } elseif ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === "'") {
                    $inString = false;
                }

                continue;
            }
            if ($ch === "'") {
                $inString = true;

                continue;
            }
            if ($ch === '(') {
                if ($depth === 0) {
                    $rows++;
                    $tupleStart = $i + 1;
                }
                $depth++;

                continue;
            }
            if ($ch === ')') {
                if ($depth === 1) {
                    $tuple = substr($vals, $tupleStart, $i - $tupleStart);
                    $comma = strpos($tuple, ',');
                    $idPart = $comma === false ? $tuple : substr($tuple, 0, $comma);
                    $idPart = trim($idPart);
                    if (ctype_digit($idPart)) {
                        $id = (int) $idPart;
                        if ($id > $maxId) {
                            $maxId = $id;
                        }
                    }
                }
                if ($depth > 0) {
                    $depth--;
                }
            }
        }
    }
    if (strlen($buffer) > 2 * 1024 * 1024) {
        $buffer = substr($buffer, -1024 * 1024);
    }
}

fclose($fh);

echo json_encode([
    'file' => $file,
    'table' => $table,
    'insert_statements' => $stmts,
    'rows' => $rows,
    'max_id' => $maxId,
], JSON_PRETTY_PRINT).PHP_EOL;
