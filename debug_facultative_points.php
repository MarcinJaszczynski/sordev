<?php
// Quick inspector for facultative points (day = duration_days + 1) in the pivot table.
// Usage: php debug_facultative_points.php [event_template_id]

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$argvId = isset($argv[1]) ? (int)$argv[1] : null;

if ($argvId) {
    echo "\n=== Details for event_template_id={$argvId} ===\n";
    $rows = DB::select(<<<SQL
        SELECT p.id as pivot_id, p.event_template_program_point_id as point_id, p.day, p.`order`, p.include_in_program, p.include_in_calculation, p.active,
               et.duration_days, et.name AS template_name,
               etpp.name AS point_name
        FROM event_template_event_template_program_point p
        JOIN event_templates et ON et.id = p.event_template_id
        LEFT JOIN event_template_program_points etpp ON etpp.id = p.event_template_program_point_id
        WHERE p.event_template_id = ?
        ORDER BY p.day ASC, p.`order` ASC
    SQL, [$argvId]);

    if (!$rows) {
        echo "No pivot rows found for template {$argvId}.\n";
        exit(0);
    }

    $byDay = [];
    foreach ($rows as $r) {
        $byDay[$r->day][] = $r;
    }
    ksort($byDay);

    foreach ($byDay as $day => $items) {
        $flag = '';
        $duration = $items[0]->duration_days ?? null;
        if ($duration !== null) {
            if ($day == $duration + 1) $flag = ' [FACULTATIVE DAY]';
            elseif ($day > $duration) $flag = ' [BEYOND DURATION]';
        }
        echo "Day {$day}{$flag}: " . count($items) . " item(s)\n";
        foreach ($items as $it) {
            echo sprintf("  - pivot:%d point:%d order:%d %s%s%s\n",
                $it->pivot_id,
                $it->point_id,
                $it->order,
                ($it->include_in_program ? '[prog] ' : ''),
                ($it->include_in_calculation ? '[calc] ' : ''),
                ($it->active ? '[active] ' : '[inactive] ')
            );
            if (!empty($it->point_name)) {
                echo "      name: ".$it->point_name."\n";
            }
        }
    }
    exit(0);
}

// Summary for all templates
$rows = DB::select(<<<SQL
    SELECT et.id, et.name, et.duration_days,
           SUM(CASE WHEN p.day = et.duration_days + 1 THEN 1 ELSE 0 END) AS facultative_count,
           SUM(CASE WHEN p.day > et.duration_days THEN 1 ELSE 0 END) AS beyond_count,
           COUNT(p.id) AS total_points
    FROM event_templates et
    LEFT JOIN event_template_event_template_program_point p ON p.event_template_id = et.id
    GROUP BY et.id, et.name, et.duration_days
    ORDER BY facultative_count DESC, beyond_count DESC, et.id ASC
SQL);

echo "=== Facultative points summary (day = duration_days + 1) ===\n";
foreach ($rows as $r) {
    printf("ID:%-4d Days:%-2d Facultative:%-3d Beyond:%-3d Total:%-4d  %s\n",
        $r->id, $r->duration_days, $r->facultative_count, $r->beyond_count, $r->total_points, $r->name);
}

echo "\nHint: Run with a template id to see details, e.g.: php debug_facultative_points.php 31\n";
