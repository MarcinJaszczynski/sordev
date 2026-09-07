@php
    use App\Filament\Resources\ContractorResource;
    use App\Models\ContractorType;
    use App\Support\LegacyContractorLookup;

    /** @var \App\Models\LegacyEvent $record */
    $record = $getRecord();
    $rows = is_array($record->contractors_json) ? $record->contractors_json : [];
    $rows = array_values(array_filter($rows, 'is_array'));

    $legacyIds = collect($rows)->pluck('contractor_id')->all();
    $resolved = LegacyContractorLookup::mapMany($legacyIds);

    $typeIds = collect($rows)
        ->map(fn ($r) => $r['contractortype_id'] ?? $r['type_id'] ?? null)
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values()
        ->all();

    $typeNames = $typeIds === []
        ? []
        : ContractorType::query()->whereIn('id', $typeIds)->pluck('name', 'id')->all();
@endphp

@if ($rows === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">Brak wykonawców</p>
@else
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full table-auto divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                    <th class="px-2 py-2 w-8">#</th>
                    <th class="px-2 py-2">Kontrahent</th>
                    <th class="px-2 py-2">Typ</th>
                    <th class="px-2 py-2">Opis</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                @foreach ($rows as $index => $row)
                    @php
                        $legacyCid = isset($row['contractor_id']) ? (int) $row['contractor_id'] : null;
                        $tid = isset($row['contractortype_id']) ? (int) $row['contractortype_id'] : (isset($row['type_id']) ? (int) $row['type_id'] : null);
                        $mapped = $legacyCid ? ($resolved[$legacyCid] ?? null) : null;
                        $name = $row['contractor_name'] ?? ($mapped['name'] ?? ($legacyCid ? '#'.$legacyCid : '—'));
                        $url = $mapped ? ContractorResource::getUrl('edit', ['record' => $mapped['id']]) : null;
                        $type = $row['type_name'] ?? ($tid ? ($typeNames[$tid] ?? '#'.$tid) : '—');
                        $desc = trim(strip_tags((string) ($row['desc'] ?? '')));
                    @endphp
                    <tr>
                        <td class="px-2 py-1.5 text-xs text-gray-400">{{ $index + 1 }}</td>
                        <td class="px-2 py-1.5 font-medium text-gray-900 dark:text-gray-100">
                            @if ($url)
                                <a href="{{ $url }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $name }}</a>
                            @else
                                {{ $name }}
                            @endif
                        </td>
                        <td class="px-2 py-1.5 text-xs text-gray-700 dark:text-gray-300">{{ $type }}</td>
                        <td class="px-2 py-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $desc !== '' ? $desc : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
