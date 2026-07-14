<?php

declare(strict_types=1);

function countInsertsByTable(string $file): array
{
    $fh = fopen($file, 'rb');
    if ($fh === false) {
        throw new RuntimeException("Cannot open {$file}");
    }

    $buffer = '';
    $tables = [];

    while (! feof($fh)) {
        $buffer .= fread($fh, 1024 * 1024) ?: '';
        while (($pos = strpos($buffer, 'INSERT')) !== false) {
            $semi = strpos($buffer, ';', $pos);
            if ($semi === false) {
                break;
            }
            $stmt = substr($buffer, $pos, $semi - $pos + 1);
            $buffer = substr($buffer, $semi + 1);

            if (! preg_match('/INSERT(?: IGNORE)? INTO `([^`]+)`/i', $stmt, $m)) {
                continue;
            }

            $table = $m[1];
            $tables[$table] = ($tables[$table] ?? 0) + 1;
        }

        if (strlen($buffer) > 2 * 1024 * 1024) {
            $buffer = substr($buffer, -1024 * 1024);
        }
    }

    fclose($fh);

    ksort($tables);

    return $tables;
}

$src = countInsertsByTable($argv[1] ?? 'pliki/host378742_sor26.sql');
$merged = countInsertsByTable($argv[2] ?? 'pliki/database.merged.sql');

$allTables = array_unique(array_merge(array_keys($src), array_keys($merged)));
sort($allTables);

$missing = [];
foreach ($allTables as $table) {
    $s = $src[$table] ?? 0;
    $m = $merged[$table] ?? 0;
    if ($s !== $m) {
        $missing[] = [$table, $s, $m, $s - $m];
    }
}

echo "Tables with different INSERT statement counts:\n";
foreach ($missing as [$table, $s, $m, $diff]) {
    echo sprintf("  %-55s src=%4d merged=%4d diff=%+d\n", $table, $s, $m, $diff);
}

echo "\nTotal src INSERT stmts: ".array_sum($src)."\n";
echo 'Total merged INSERT stmts: '.array_sum($merged)."\n";
