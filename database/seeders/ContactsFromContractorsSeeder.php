<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class ContactsFromContractorsSeeder extends Seeder
{
    public function run(): void
    {
        // ensure contacts table exists (minimal schema) - safe for sqlite dev
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS contacts (
  id integer primary key autoincrement,
  created_at datetime,
  updated_at datetime,
  first_name varchar(255),
  last_name varchar(255),
  "function" varchar(255),
  phone varchar(255),
  email varchar(255),
  street varchar(255),
  city varchar(255),
  region varchar(255),
  note varchar(255)
);
SQL
        );

        // ensure pivot exists
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS contractor_contact (
  id integer primary key autoincrement,
  contractor_id integer not null,
  contact_id integer not null,
  created_at datetime,
  updated_at datetime
);
SQL
        );

        $contractors = DB::table('contractors')->get();
        $created = 0;
        $linked = 0;

        foreach ($contractors as $c) {
            $first = $c->firstname ?: null;
            $last = $c->surname ?: null;
            // try to extract from name if missing
            if (empty($first) && !empty($c->name)) {
                $parts = preg_split('/\s+/', trim($c->name));
                if (count($parts) === 1) {
                    $first = $parts[0];
                } else {
                    $first = $parts[0];
                    $last = $last ?: end($parts);
                }
            }

            $phone = $c->phone ?? null;
            $email = $c->email ?? null;

            // skip if no meaningful contact data
            if (empty($first) && empty($last) && empty($phone) && empty($email)) {
                continue;
            }

            // try to reuse existing contact
            $query = DB::table('contacts');
            if (!empty($email)) {
                $query->where('email', $email);
            } elseif (!empty($phone)) {
                $query->where('phone', $phone);
            } else {
                $query->where('first_name', $first)->where('last_name', $last);
            }
            $existing = $query->first();

            // collect contractor types for this contractor (may be used whether contact exists or is created)
            $typesList = [];
            if (Schema::hasTable('contractor_types') && Schema::hasTable('contractor_contractortype')) {
                try {
                    $typesList = DB::table('contractor_types')
                        ->join('contractor_contractortype', 'contractor_types.id', '=', 'contractor_contractortype.contractor_type_id')
                        ->where('contractor_contractortype.contractor_id', $c->id)
                        ->pluck('contractor_types.name')
                        ->filter()
                        ->toArray();
                } catch (\Throwable $e) {
                    $typesList = [];
                }
            }

            if ($existing) {
                $contactId = $existing->id;

                // append types to existing contact note/notes if available
                if (!empty($typesList)) {
                    // detect note column name
                    $pragma = DB::select("PRAGMA table_info('contacts')");
                    $existingCols = array_map(function ($r) { return $r->name; }, $pragma);
                    $noteCol = in_array('notes', $existingCols, true) ? 'notes' : (in_array('note', $existingCols, true) ? 'note' : null);
                    if ($noteCol) {
                        $current = DB::table('contacts')->where('id', $contactId)->value($noteCol);
                        $typesStr = implode(', ', $typesList);
                        $new = trim((string)$current);
                        if ($new === '') {
                            $new = "import z poprzedniej wersji; typy: $typesStr";
                        } else {
                            // avoid duplicating
                            if (strpos($new, $typesStr) === false) {
                                $new = $new . "; typy: $typesStr";
                            }
                        }
                        DB::table('contacts')->where('id', $contactId)->update([$noteCol => $new]);
                    }
                }
            } else {
                // detect existing columns in contacts table (sqlite friendly)
                $pragma = DB::select("PRAGMA table_info('contacts')");
                $existingCols = array_map(function ($r) {
                    return $r->name;
                }, $pragma);

                $now = date('Y-m-d H:i:s');
                $allData = [
                    'created_at' => $c->created_at ?: $now,
                    'updated_at' => $c->updated_at ?: $now,
                    'first_name' => $first,
                    'last_name' => $last,
                    'function' => null,
                    'phone' => $phone,
                    'email' => $email,
                    'street' => $c->street ?? null,
                    'city' => $c->city ?? null,
                    'region' => $c->region ?? null,
                    'note' => 'import z poprzedniej wersji',
                ];

                // only keep keys that the table actually has
                $contactData = [];
                foreach ($allData as $k => $v) {
                    if (in_array($k, $existingCols, true)) {
                        $contactData[$k] = $v;
                    }
                }

                // attach contractor types to note if available
                $typesList = [];
                if (Schema::hasTable('contractor_types') && Schema::hasTable('contractor_contractortype')) {
                    try {
                        $typesList = DB::table('contractor_types')
                            ->join('contractor_contractortype', 'contractor_types.id', '=', 'contractor_contractortype.contractor_type_id')
                            ->where('contractor_contractortype.contractor_id', $c->id)
                            ->pluck('contractor_types.name')
                            ->filter()
                            ->toArray();
                    } catch (\Throwable $e) {
                        $typesList = [];
                    }
                }

                if (!empty($typesList)) {
                    $typesStr = implode(', ', $typesList);
                    if (isset($contactData['note'])) {
                        $contactData['note'] = trim($contactData['note']) . "; typy: $typesStr";
                    } else {
                        // if note column not present but 'note' was in allData then it was removed; try to add if column exists
                        if (in_array('note', $existingCols, true)) {
                            $contactData['note'] = "import z poprzedniej wersji; typy: $typesStr";
                        }
                    }
                }

                // fallback: ensure created_at/updated_at if possible
                if (!isset($contactData['created_at']) && in_array('created_at', $existingCols, true)) {
                    $contactData['created_at'] = $now;
                }
                if (!isset($contactData['updated_at']) && in_array('updated_at', $existingCols, true)) {
                    $contactData['updated_at'] = $now;
                }

                $contactId = DB::table('contacts')->insertGetId($contactData);
                $created++;
            }

            // link in pivot (avoid duplicates)
            DB::table('contractor_contact')->insertOrIgnore([
                'contractor_id' => $c->id,
                'contact_id' => $contactId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $linked++;
        }

    echo "Contacts created: $created, linked: $linked\n";
    }
}
