<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ActivateEventTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * You can customize the $templateIds array below with the IDs you want to (re)activate.
     * Logic:
     *  - Set is_active = 1
     *  - If slug missing or empty -> build slug from name (ascii, unique by appending -{id} if conflict)
     *  - If duration_days null/0 and there is an event_template_qty reference -> infer minimal qty-based length (fallback 1)
     *  - Ensure created_at/updated_at untouched except updated_at refresh
     */
    public function run(): void
    {
        // IDs docelowych szablonów do aktywacji (dostosuj według potrzeb)
        $templateIds = [155];

        $now = now();
        $templates = DB::table('event_templates')->whereIn('id', $templateIds)->get();

        foreach ($templates as $tpl) {
            $updates = ['is_active' => 1, 'updated_at' => $now];

            // Uzupełnij slug jeśli pusty
            if (!$tpl->slug || trim($tpl->slug) === '') {
                $base = Str::slug($tpl->name ?: ('szablon-' . $tpl->id));
                if ($base === '') {
                    $base = 'et-' . $tpl->id;
                }
                $slug = $base;
                $conflict = 0;
                while (DB::table('event_templates')->where('slug', $slug)->where('id', '!=', $tpl->id)->exists()) {
                    $conflict++;
                    $slug = $base . '-' . $conflict;
                }
                $updates['slug'] = $slug;
            }

            // Ustal duration_days jeśli brak
            if (empty($tpl->duration_days) || (int)$tpl->duration_days <= 0) {
                // Spróbuj wydedukować: jeśli istnieje pole 'duration' albo jakiekolwiek program points z pivot 'day'
                $duration = null;
                // Źródło 1: kolumna legacy 'length_days' (jeżeli istnieje)
                if (Schema::hasColumn('event_templates', 'length_days') && $tpl->length_days) {
                    $duration = (int)$tpl->length_days;
                }
                // Źródło 2: pivot program points
                if ($duration === null) {
                    $maxDay = DB::table('event_template_event_template_program_point')
                        ->where('event_template_id', $tpl->id)
                        ->max('day');
                    if ($maxDay) {
                        $duration = (int)$maxDay;
                    }
                }
                // Fallback
                if ($duration === null || $duration <= 0) {
                    $duration = 1;
                }
                $updates['duration_days'] = $duration;
            }

            DB::table('event_templates')->where('id', $tpl->id)->update($updates);
            echo "Updated template ID {$tpl->id}\n";
        }

        echo "ActivateEventTemplatesSeeder completed.\n";
    }
}
