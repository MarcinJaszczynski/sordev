<?php

declare(strict_types=1);

/**
 * Build a merged SQL dump file without using a live MySQL instance.
 *
 * Output = destination structure dump (kept as-is) + filtered INSERTs from source data dump.
 *
 * - Destination dump should be the "good structure" (pliki/database.sql).
 * - Source dump should be the "server data" (pliki/host378742_sor26.sql).
 *
 * It rewrites INSERT statements from source:
 * - skips tables not present in destination schema
 * - keeps only columns that exist in destination schema
 * - converts to INSERT IGNORE to reduce duplicate-key failures on server import
 *
 * Usage:
 *   php scripts/build_merged_sql_dump.php pliki/host378742_sor26.sql pliki/database.sql pliki/database.merged.sql
 */
if ($argc < 4) {
    fwrite(STDERR, "Usage: php scripts/build_merged_sql_dump.php <src_dump.sql> <dst_dump.sql> <out.sql>\n");
    exit(2);
}

$srcPath = $argv[1];
$dstPath = $argv[2];
$outPath = $argv[3];

if (! is_file($srcPath)) {
    fwrite(STDERR, "Source dump not found: {$srcPath}\n");
    exit(3);
}
if (! is_file($dstPath)) {
    fwrite(STDERR, "Destination dump not found: {$dstPath}\n");
    exit(4);
}

@mkdir(dirname($outPath), 0775, true);

/**
 * Parse destination dump to get table => set(columns).
 * We only need column names to filter source INSERTs.
 *
 * This is a tolerant parser for dumps formatted like:
 *   CREATE TABLE `table` (
 *     `col` type ...,
 *     ...
 *   ) ENGINE=...
 */
function parseDestinationSchema(string $dstPath): array
{
    $fh = fopen($dstPath, 'rb');
    if ($fh === false) {
        throw new RuntimeException("Cannot open destination: {$dstPath}");
    }

    $tables = [];
    $currentTable = null;
    $collectCols = false;

    while (($line = fgets($fh)) !== false) {
        if ($currentTable === null) {
            if (preg_match('/^CREATE TABLE `([^`]+)` \(/', $line, $m)) {
                $currentTable = $m[1];
                $tables[$currentTable] = [];
                $collectCols = true;

                continue;
            }

            continue;
        }

        if ($collectCols) {
            if (preg_match('/^\\s*`([^`]+)`\\s+/', $line, $m)) {
                $tables[$currentTable][$m[1]] = true;

                continue;
            }
            if (preg_match('/^\\)\\s*/', $line)) {
                $currentTable = null;
                $collectCols = false;

                continue;
            }
        }
    }

    fclose($fh);

    return $tables;
}

/**
 * Split "(v1,v2,...),(v1,v2,...)" into array of tuple strings including parentheses.
 * Works by scanning, respecting quotes and escapes.
 */
function splitTuples(string $valuesSql): array
{
    $tuples = [];
    $len = strlen($valuesSql);
    $i = 0;

    $inString = false;
    $escape = false;
    $depth = 0;
    $start = null;

    while ($i < $len) {
        $ch = $valuesSql[$i];

        if ($inString) {
            if ($escape) {
                $escape = false;
            } elseif ($ch === '\\\\') {
                $escape = true;
            } elseif ($ch === "'") {
                $inString = false;
            }
            $i++;

            continue;
        }

        if ($ch === "'") {
            $inString = true;
            $i++;

            continue;
        }

        if ($ch === '(') {
            if ($depth === 0) {
                $start = $i;
            }
            $depth++;
            $i++;

            continue;
        }

        if ($ch === ')') {
            $depth--;
            if ($depth === 0 && $start !== null) {
                $tuples[] = substr($valuesSql, $start, $i - $start + 1);
                $start = null;
            }
            $i++;

            continue;
        }

        $i++;
    }

    return $tuples;
}

/**
 * Split a tuple like "(a,'b,c',NULL)" into array of raw value SQL fragments.
 */
function splitTupleValues(string $tupleSql): array
{
    $s = trim($tupleSql);
    if ($s === '' || $s[0] !== '(' || substr($s, -1) !== ')') {
        throw new InvalidArgumentException("Invalid tuple: {$tupleSql}");
    }
    $inner = substr($s, 1, -1);
    $len = strlen($inner);
    $i = 0;
    $vals = [];
    $buf = '';

    $inString = false;
    $escape = false;
    $depth = 0; // for function calls / parentheses in values (rare), but keep safe

    while ($i < $len) {
        $ch = $inner[$i];

        if ($inString) {
            $buf .= $ch;
            if ($escape) {
                $escape = false;
            } elseif ($ch === '\\\\') {
                $escape = true;
            } elseif ($ch === "'") {
                $inString = false;
            }
            $i++;

            continue;
        }

        if ($ch === "'") {
            $inString = true;
            $buf .= $ch;
            $i++;

            continue;
        }

        if ($ch === '(') {
            $depth++;
            $buf .= $ch;
            $i++;

            continue;
        }
        if ($ch === ')') {
            if ($depth > 0) {
                $depth--;
            }
            $buf .= $ch;
            $i++;

            continue;
        }

        if ($ch === ',' && $depth === 0) {
            $vals[] = trim($buf);
            $buf = '';
            $i++;

            continue;
        }

        $buf .= $ch;
        $i++;
    }

    if (trim($buf) !== '' || $inner === '') {
        $vals[] = trim($buf);
    }

    return $vals;
}

function writeLine($out, string $line): void
{
    fwrite($out, $line);
    if (! str_ends_with($line, "\n")) {
        fwrite($out, "\n");
    }
}

$dstSchema = parseDestinationSchema($dstPath);

$out = fopen($outPath, 'wb');
if ($out === false) {
    fwrite(STDERR, "Cannot open output: {$outPath}\n");
    exit(5);
}

// 1) Copy destination dump as-is (structure + whatever baseline data it includes)
$dstIn = fopen($dstPath, 'rb');
if ($dstIn === false) {
    fwrite(STDERR, "Cannot read destination: {$dstPath}\n");
    exit(6);
}
// We copy only structure from dst (skip its INSERTs) to avoid duplicating data.
$dstBuffer = '';
$skipInsert = false;
while (! feof($dstIn)) {
    $chunk = fread($dstIn, 1024 * 1024);
    if ($chunk === false) {
        break;
    }
    if ($chunk === '') {
        continue;
    }

    $dstBuffer .= $chunk;

    // Process line-by-line to safely skip INSERT blocks.
    while (($nlPos = strpos($dstBuffer, "\n")) !== false) {
        $line = substr($dstBuffer, 0, $nlPos + 1);
        $dstBuffer = substr($dstBuffer, $nlPos + 1);

        if ($skipInsert) {
            // End of INSERT statement (could be multi-line).
            if (strpos($line, ';') !== false) {
                $skipInsert = false;
            }

            continue;
        }

        if (preg_match('/^INSERT INTO\\s+`/i', $line)) {
            $skipInsert = true;
            if (strpos($line, ';') !== false) {
                $skipInsert = false;
            }

            continue;
        }

        // Drop mysqldump footer from structure section; we re-enable checks after data.
        if (preg_match('/FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS/i', $line)) {
            continue;
        }
        if (preg_match('/UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS/i', $line)) {
            continue;
        }
        if (preg_match('/SQL_NOTES=@OLD_SQL_NOTES/i', $line)) {
            continue;
        }
        if (preg_match('/^-- Dump completed on /i', $line)) {
            continue;
        }

        fwrite($out, $line);
    }
}

// Flush any remaining buffer (no newline at end)
if ($dstBuffer !== '') {
    if (! $skipInsert && ! preg_match('/^INSERT INTO\\s+`/i', $dstBuffer)) {
        fwrite($out, $dstBuffer);
    }
}
fclose($dstIn);

writeLine($out, "\n-- ----------------------------------------------------------------------");
writeLine($out, "-- MERGED DATA FROM: {$srcPath}");
writeLine($out, "-- Filtered to destination schema: {$dstPath}");
writeLine($out, '-- Strategy: INSERT IGNORE + common columns only');
writeLine($out, '-- ----------------------------------------------------------------------');
writeLine($out, 'SET FOREIGN_KEY_CHECKS=0;');
writeLine($out, 'SET UNIQUE_CHECKS=0;');
writeLine($out, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';");
writeLine($out, '');

// 2) Stream-parse source dump to find and rewrite INSERT statements.
$srcIn = fopen($srcPath, 'rb');
if ($srcIn === false) {
    fwrite(STDERR, "Cannot read source: {$srcPath}\n");
    exit(7);
}

$buffer = '';
$insertCount = 0;
$skippedNoTable = 0;
$skippedNoCommon = 0;
$skippedUnparsed = 0;
$rewritten = 0;

/**
 * Attempt to extract a complete INSERT statement from buffer.
 * Returns [stmt, rest] or [null, buffer] when incomplete.
 */
function extractInsertStatement(string $buffer): array
{
    $pos = strpos($buffer, 'INSERT INTO ');
    if ($pos === false) {
        // keep buffer bounded
        if (strlen($buffer) > 2 * 1024 * 1024) {
            $buffer = substr($buffer, -1024 * 1024);
        }

        return [null, $buffer];
    }

    // discard anything before INSERT INTO (we only care about INSERTs from src)
    if ($pos > 0) {
        $buffer = substr($buffer, $pos);
    }

    // find terminating semicolon not inside a string
    $len = strlen($buffer);
    $inString = false;
    $escape = false;
    for ($i = 0; $i < $len; $i++) {
        $ch = $buffer[$i];
        if ($inString) {
            if ($escape) {
                $escape = false;
            } elseif ($ch === '\\\\') {
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
        if ($ch === ';') {
            $stmt = substr($buffer, 0, $i + 1);
            $rest = substr($buffer, $i + 1);

            return [$stmt, $rest];
        }
    }

    return [null, $buffer];
}

while (! feof($srcIn)) {
    $chunk = fread($srcIn, 1024 * 1024);
    if ($chunk === false) {
        break;
    }
    $buffer .= $chunk;

    while (true) {
        [$stmt, $buffer] = extractInsertStatement($buffer);
        if ($stmt === null) {
            break;
        }

        // Parse: INSERT INTO `table` (`a`,`b`,...) VALUES (...),(...);
        if (! preg_match('/^INSERT INTO `([^`]+)`\\s*\\(([^\\)]+)\\)\\s*VALUES\\s*(.+);\\s*$/s', trim($stmt), $m)) {
            $skippedUnparsed++;

            continue;
        }

        $table = $m[1];
        if (! isset($dstSchema[$table])) {
            $skippedNoTable++;

            continue;
        }

        $colsRaw = $m[2];
        preg_match_all('/`([^`]+)`/', $colsRaw, $mm);
        $srcCols = $mm[1] ?? [];
        if ($srcCols === []) {
            continue;
        }

        $dstColsSet = $dstSchema[$table];
        $keepIdx = [];
        $keepCols = [];
        foreach ($srcCols as $idx => $col) {
            if (isset($dstColsSet[$col])) {
                $keepIdx[] = $idx;
                $keepCols[] = $col;
            }
        }

        if ($keepCols === []) {
            $skippedNoCommon++;

            continue;
        }

        $valuesSql = trim($m[3]);
        $tuples = splitTuples($valuesSql);
        if ($tuples === []) {
            continue;
        }

        $outTuples = [];
        foreach ($tuples as $t) {
            $vals = splitTupleValues($t);
            if (count($vals) !== count($srcCols)) {
                // shape mismatch; skip this tuple
                continue;
            }
            $kept = [];
            foreach ($keepIdx as $i) {
                $kept[] = $vals[$i];
            }
            $outTuples[] = '('.implode(', ', $kept).')';
        }

        if ($outTuples === []) {
            continue;
        }

        $colsSql = implode(', ', array_map(fn ($c) => '`'.str_replace('`', '``', $c).'`', $keepCols));
        $insert = "INSERT IGNORE INTO `{$table}` ({$colsSql}) VALUES\n".implode(",\n", $outTuples).";\n";

        fwrite($out, $insert);
        $insertCount++;

        if (count($keepCols) !== count($srcCols)) {
            $rewritten++;
        }
    }
}

fclose($srcIn);

writeLine($out, 'SET FOREIGN_KEY_CHECKS=1;');
writeLine($out, 'SET UNIQUE_CHECKS=1;');

fclose($out);

fwrite(STDERR, "Merged dump created: {$outPath}\n");
fwrite(STDERR, "INSERT statements written: {$insertCount}\n");
fwrite(STDERR, "Rewritten (filtered columns): {$rewritten}\n");
fwrite(STDERR, "Skipped (table not in dst): {$skippedNoTable}\n");
fwrite(STDERR, "Skipped (no common columns): {$skippedNoCommon}\n");
fwrite(STDERR, "Skipped (unparsed INSERT format): {$skippedUnparsed}\n");
