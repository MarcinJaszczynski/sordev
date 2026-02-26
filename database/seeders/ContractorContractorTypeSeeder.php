<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContractorContractorTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Try to parse mappings directly from the SQL dump if available.
        $dumpPath = base_path('sorstary/host803729_bazasor.sql');
        $mappings = [];

        if (file_exists($dumpPath) && is_readable($dumpPath)) {
            $content = file_get_contents($dumpPath);

            // Find all INSERT statements for contractor_contractortype
            if (preg_match_all('/INSERT INTO `contractor_contractortype`[^;]*;/is', $content, $inserts)) {
                foreach ($inserts[0] as $insertSql) {
                    // Extract all parenthesized value groups
                    if (preg_match_all('/\(([^)]+)\)/', $insertSql, $rows)) {
                        foreach ($rows[1] as $row) {
                            // Split by comma; pivot rows are simple numeric/NULL values so this is safe
                            $parts = array_map('trim', explode(',', $row));
                            // Expecting: id, created_at, updated_at, contractor_id, contractor_type_id
                            if (count($parts) < 5) {
                                continue;
                            }

                            // Skip parenthesized column list or other non-data parentheses: first value must be numeric
                            $firstRaw = trim($parts[0]);
                            $firstClean = trim($firstRaw, "'\" `");
                            if (!preg_match('/^-?\d+$/', $firstClean)) {
                                continue;
                            }

                            $parse = function ($v) {
                                $v = trim($v);
                                if (strcasecmp($v, 'NULL') === 0) {
                                    return null;
                                }
                                // strip surrounding quotes
                                $v = trim($v, "'\" ");
                                return $v === '' ? null : $v;
                            };

                            $id = (int) $parse($parts[0]);
                            $created_at = $parse($parts[1]);
                            $updated_at = $parse($parts[2]);
                            $contractor_id = (int) $parse($parts[3]);
                            $contractor_type_id = (int) $parse($parts[4]);

                            $mappings[] = [
                                'id' => $id,
                                'created_at' => $created_at,
                                'updated_at' => $updated_at,
                                'contractor_id' => $contractor_id,
                                'contractor_type_id' => $contractor_type_id,
                            ];
                        }
                    }
                }
            }
        }

        // Fallback sample if dump not available or parsing failed
        if (empty($mappings)) {
            $mappings = [
                ['id'=>1,'contractor_id'=>1,'contractor_type_id'=>6,'created_at'=>null,'updated_at'=>null],
                ['id'=>2,'contractor_id'=>2,'contractor_type_id'=>1,'created_at'=>null,'updated_at'=>null],
                ['id'=>3,'contractor_id'=>2,'contractor_type_id'=>2,'created_at'=>null,'updated_at'=>null],
                ['id'=>4,'contractor_id'=>2,'contractor_type_id'=>3,'created_at'=>null,'updated_at'=>null],
            ];
        }

        // Filter mappings to those that reference existing contractors and contractor_types
        $contractorIds = array_values(array_unique(array_map(fn($m) => $m['contractor_id'] ?? null, $mappings)));
        $typeIds = array_values(array_unique(array_map(fn($m) => $m['contractor_type_id'] ?? null, $mappings)));

        $existingContractorIds = DB::table('contractors')->whereIn('id', $contractorIds)->pluck('id')->toArray();
        $existingTypeIds = DB::table('contractor_types')->whereIn('id', $typeIds)->pluck('id')->toArray();

        $filtered = array_values(array_filter($mappings, function ($m) use ($existingContractorIds, $existingTypeIds) {
            return in_array($m['contractor_id'], $existingContractorIds, true)
                && in_array($m['contractor_type_id'], $existingTypeIds, true);
        }));

        if (empty($filtered)) {
            // nothing to insert (likely because contractors or types not seeded yet)
            info('ContractorContractorTypeSeeder: no matching mappings found for existing contractors or types.');
            return;
        }

        foreach (array_chunk($filtered, 200) as $chunk) {
            DB::table('contractor_contractortype')->insertOrIgnore($chunk);
        }
    }
}
