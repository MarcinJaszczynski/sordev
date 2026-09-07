@php
    use Illuminate\Support\Str;

    /** @var \App\Models\LegacyEvent $record */
    $record = $getRecord();
    $rows = is_array($record->notes_json) ? $record->notes_json : [];
    $rows = array_values(array_filter($rows, 'is_array'));

    $plain = static function (mixed $value, int $limit = 200): string {
        if (blank($value)) {
            return '';
        }
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $value))) ?? '');

        return $text === '' ? '' : Str::limit($text, $limit);
    };
@endphp

@if ($rows === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">Brak notatek</p>
@else
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full table-auto divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                    <th class="px-2 py-2 w-8">#</th>
                    <th class="px-2 py-2">Tytuł</th>
                    <th class="px-2 py-2">Treść</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                @foreach ($rows as $index => $row)
                    <tr class="align-top">
                        <td class="px-2 py-1.5 text-xs text-gray-400">{{ $index + 1 }}</td>
                        <td class="px-2 py-1.5 font-medium text-gray-900 dark:text-gray-100">
                            {{ $plain($row['name'] ?? null, 160) ?: '—' }}
                        </td>
                        <td class="px-2 py-1.5 text-xs text-gray-600 dark:text-gray-300">
                            {{ $plain($row['description'] ?? null, 220) ?: '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
