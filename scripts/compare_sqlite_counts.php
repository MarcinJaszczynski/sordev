<?php
if ($argc < 3) {
    echo "Usage: php compare_sqlite_counts.php <src_db> <dst_db>\n";
    exit(2);
}
$src = $argv[1];
$dst = $argv[2];
if (!file_exists($src)) { echo "Source not found: $src\n"; exit(3); }
if (!file_exists($dst)) { echo "Destination not found: $dst\n"; exit(4); }
try {
    $s = new PDO('sqlite:' . $src);
    $d = new PDO('sqlite:' . $dst);
    $s->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $d->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tablesSrc = $s->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
    $tablesDst = $d->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);

    $all = array_values(array_unique(array_merge($tablesSrc, $tablesDst)));
    sort($all);

    echo "Comparing tables (src: $src) -> (dst: $dst)\n\n";
    $missing = [];
    $extra = [];
    $differences = [];
    foreach ($all as $t) {
        $inSrc = in_array($t, $tablesSrc);
        $inDst = in_array($t, $tablesDst);
        $srcCount = $inSrc ? (int)$s->query("SELECT COUNT(*) FROM \"$t\"")->fetchColumn() : null;
        $dstCount = $inDst ? (int)$d->query("SELECT COUNT(*) FROM \"$t\"")->fetchColumn() : null;
        printf("%-40s src=%7s  dst=%7s  diff=%+7s\n", $t, $inSrc? $srcCount : 'N/A', $inDst? $dstCount : 'N/A', ($srcCount !== null && $dstCount !== null) ? ($dstCount - $srcCount) : 'N/A');
        if ($inSrc && !$inDst) $missing[] = $t;
        if (!$inSrc && $inDst) $extra[] = $t;
        if ($inSrc && $inDst && $dstCount < $srcCount) $differences[$t] = ['src' => $srcCount, 'dst' => $dstCount];
    }

    echo "\nSummary:\n";
    echo "Tables only in source: " . count($missing) . "\n";
    echo "Tables only in destination: " . count($extra) . "\n";
    echo "Tables with fewer rows in destination: " . count($differences) . "\n\n";
    if (!empty($differences)) {
        echo "Top differences:\n";
        uasort($differences, function($a,$b){ return ($b['src'] - $b['dst']) - ($a['src'] - $a['dst']); });
        $i=0;
        foreach ($differences as $t => $v) {
            $i++; printf("%2d) %-40s src=%7d dst=%7d missing=%7d\n", $i, $t, $v['src'], $v['dst'], $v['src'] - $v['dst']);
            if ($i >= 15) break;
        }
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(5);
}
