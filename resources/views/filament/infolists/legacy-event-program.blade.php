@php
    use App\Filament\Resources\ContractorResource;
    use App\Support\LegacyContractorLookup;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    /** @var \App\Models\LegacyEvent $record */
    $record = $getRecord();
    $rows = is_array($record->elements_json) ? $record->elements_json : [];
    $rows = array_values(array_filter($rows, 'is_array'));

    $resolved = LegacyContractorLookup::mapMany(collect($rows)->pluck('contractor_id')->all());

    $formatDt = static function (mixed $value): string {
        if (blank($value)) {
            return '—';
        }
        try {
            return Carbon::parse((string) $value)->format('d.m.Y H:i');
        } catch (Throwable) {
            return (string) $value;
        }
    };

    $plain = static function (mixed $value, int $limit = 120): string {
        if (blank($value)) {
            return '';
        }
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $value))) ?? '');

        return $text === '' ? '' : Str::limit($text, $limit);
    };

    $money = static function (mixed $value): string {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 2, ',', ' ').' zł';
    };
@endphp

@if ($rows === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">Brak elementów programu</p>
@else
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                    <th class="px-2 py-2 w-8">#</th>
                    <th class="px-2 py-2 min-w-[10rem]">Pozycja</th>
                    <th class="px-2 py-2 whitespace-nowrap">Start</th>
                    <th class="px-2 py-2 whitespace-nowrap">Koniec</th>
                    <th class="px-2 py-2 text-right whitespace-nowrap">Ilość</th>
                    <th class="px-2 py-2 text-right whitespace-nowrap">Koszt</th>
                    <th class="px-2 py-2">Płatnik</th>
                    <th class="px-2 py-2">Status</th>
                    <th class="px-2 py-2">Kontrahent</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                @foreach ($rows as $index => $row)
                    @php
                        $name = $row['element_name'] ?? $row['name'] ?? '—';
                        $desc = $plain($row['eventElementDescription'] ?? $row['description'] ?? null, 140);
                        $qty = $row['eventElementCostQty'] ?? $row['qty'] ?? null;
                        $cost = $row['eventElementCost'] ?? $row['cost'] ?? null;
                        $payer = $row['eventElementCostPayer'] ?? $row['cost_payer'] ?? null;
                        $status = $row['eventElementCostStatus'] ?? $row['cost_status'] ?? null;
                        $cid = isset($row['contractor_id']) ? (int) $row['contractor_id'] : null;
                        $mapped = $cid ? ($resolved[$cid] ?? null) : null;
                        $contractor = $row['contractor_name']
                            ?? ($mapped['name'] ?? ($cid ? '#'.$cid : null));
                        $contractorUrl = $mapped ? ContractorResource::getUrl('edit', ['record' => $mapped['id']]) : null;
                        $active = array_key_exists('active', $row) ? (bool) $row['active'] : true;
                    @endphp
                    <tr @class([
                        'align-top',
                        'opacity-50' => ! $active,
                    ])>
                        <td class="px-2 py-1.5 text-xs text-gray-400">{{ $index + 1 }}</td>
                        <td class="px-2 py-1.5">
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $name }}</div>
                            @if ($desc !== '')
                                <div class="mt-0.5 text-xs leading-snug text-gray-500 dark:text-gray-400">{{ $desc }}</div>
                            @endif
                        </td>
                        <td class="px-2 py-1.5 whitespace-nowrap text-xs text-gray-700 dark:text-gray-300">
                            {{ $formatDt($row['eventElementStart'] ?? $row['start'] ?? null) }}
                        </td>
                        <td class="px-2 py-1.5 whitespace-nowrap text-xs text-gray-700 dark:text-gray-300">
                            {{ $formatDt($row['eventElementEnd'] ?? $row['end'] ?? null) }}
                        </td>
                        <td class="px-2 py-1.5 text-right tabular-nums text-gray-700 dark:text-gray-300">
                            {{ $qty !== null && $qty !== '' ? $qty : '—' }}
                        </td>
                        <td class="px-2 py-1.5 text-right tabular-nums whitespace-nowrap text-gray-900 dark:text-gray-100">
                            {{ $money($cost) }}
                        </td>
                        <td class="px-2 py-1.5 text-xs text-gray-700 dark:text-gray-300">{{ $payer ?: '—' }}</td>
                        <td class="px-2 py-1.5 text-xs text-gray-700 dark:text-gray-300">{{ $status ?: '—' }}</td>
                        <td class="px-2 py-1.5 text-xs text-gray-700 dark:text-gray-300">
                            @if ($contractorUrl)
                                <a href="{{ $contractorUrl }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $contractor }}</a>
                            @else
                                {{ $contractor ?: '—' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
