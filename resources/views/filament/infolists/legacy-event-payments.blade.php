@php
    use App\Filament\Resources\ContractorResource;
    use App\Support\LegacyContractorLookup;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    /** @var \App\Models\LegacyEvent $record */
    $record = $getRecord();
    $rows = is_array($record->payments_json) ? $record->payments_json : [];
    $rows = array_values(array_filter($rows, 'is_array'));

    $resolved = LegacyContractorLookup::mapMany(collect($rows)->pluck('contractor_id')->all());

    $formatDate = static function (mixed $value): string {
        if (blank($value)) {
            return '—';
        }
        try {
            return Carbon::parse((string) $value)->format('d.m.Y');
        } catch (Throwable) {
            return (string) $value;
        }
    };

    $plain = static function (mixed $value, int $limit = 100): string {
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

    $lineTotal = static function (mixed $qty, mixed $price): ?float {
        if ($qty === null || $qty === '' || $price === null || $price === '') {
            return null;
        }

        return round(((float) $qty) * ((float) $price), 2);
    };

    $plannedSum = 0.0;
    $actualSum = 0.0;
@endphp

@if ($rows === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">Brak wydatków</p>
@else
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                    <th class="px-2 py-2 w-8">#</th>
                    <th class="px-2 py-2 min-w-[9rem]">Nazwa</th>
                    <th class="px-2 py-2">Kontrahent</th>
                    <th class="px-2 py-2">Płatnik</th>
                    <th class="px-2 py-2 whitespace-nowrap">Data</th>
                    <th class="px-2 py-2 text-right whitespace-nowrap">Plan</th>
                    <th class="px-2 py-2 text-right whitespace-nowrap">Wykonanie</th>
                    <th class="px-2 py-2">Status</th>
                    <th class="px-2 py-2">Uwagi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                @foreach ($rows as $index => $row)
                    @php
                        $name = $row['paymentName'] ?? $row['name'] ?? '—';
                        $desc = $plain($row['paymentDescription'] ?? $row['description'] ?? null, 90);
                        $note = $plain($row['paymentNote'] ?? $row['note'] ?? null, 80);
                        $payer = $row['payer'] ?? null;
                        $cid = isset($row['contractor_id']) ? (int) $row['contractor_id'] : null;
                        $mapped = $cid ? ($resolved[$cid] ?? null) : null;
                        $contractor = $row['contractor_name'] ?? ($mapped['name'] ?? ($cid ? '#'.$cid : null));
                        $contractorUrl = $mapped ? ContractorResource::getUrl('edit', ['record' => $mapped['id']]) : null;
                        $plannedQty = $row['plannedQty'] ?? $row['planned_qty'] ?? null;
                        $plannedPrice = $row['plannedPrice'] ?? $row['planned_price'] ?? null;
                        $qty = $row['qty'] ?? null;
                        $price = $row['price'] ?? null;
                        $planned = $lineTotal($plannedQty, $plannedPrice);
                        $actual = $lineTotal($qty, $price);
                        if ($planned !== null) {
                            $plannedSum += $planned;
                        }
                        if ($actual !== null) {
                            $actualSum += $actual;
                        }
                        $statusRaw = $row['paymentStatus'] ?? $row['status'] ?? null;
                        $status = match (true) {
                            $statusRaw === 1 || $statusRaw === '1' || $statusRaw === true => 'Opłacone',
                            $statusRaw === 0 || $statusRaw === '0' || $statusRaw === false => 'Nieopłacone',
                            filled($statusRaw) => (string) $statusRaw,
                            default => '—',
                        };
                        $advance = ! empty($row['advance']);
                        $accepted = ! empty($row['accepted']);
                    @endphp
                    <tr class="align-top">
                        <td class="px-2 py-1.5 text-xs text-gray-400">{{ $index + 1 }}</td>
                        <td class="px-2 py-1.5">
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $name }}
                                @if ($advance)
                                    <span class="ml-1 rounded bg-amber-100 px-1 py-0.5 text-[10px] font-semibold uppercase text-amber-800">zaliczka</span>
                                @endif
                            </div>
                            @if ($desc !== '')
                                <div class="mt-0.5 text-xs leading-snug text-gray-500 dark:text-gray-400">{{ $desc }}</div>
                            @endif
                        </td>
                        <td class="px-2 py-1.5 text-xs text-gray-700 dark:text-gray-300">
                            @if ($contractorUrl)
                                <a href="{{ $contractorUrl }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $contractor }}</a>
                            @else
                                {{ $contractor ?: '—' }}
                            @endif
                        </td>
                        <td class="px-2 py-1.5 text-xs capitalize text-gray-700 dark:text-gray-300">{{ $payer ?: '—' }}</td>
                        <td class="px-2 py-1.5 whitespace-nowrap text-xs text-gray-700 dark:text-gray-300">
                            {{ $formatDate($row['paymentDate'] ?? $row['date'] ?? null) }}
                        </td>
                        <td class="px-2 py-1.5 text-right tabular-nums whitespace-nowrap text-gray-700 dark:text-gray-300">
                            <div>{{ $money($planned) }}</div>
                            @if ($plannedQty !== null && $plannedPrice !== null)
                                <div class="text-[10px] text-gray-400">{{ $plannedQty }} × {{ number_format((float) $plannedPrice, 2, ',', ' ') }}</div>
                            @endif
                        </td>
                        <td class="px-2 py-1.5 text-right tabular-nums whitespace-nowrap text-gray-900 dark:text-gray-100">
                            <div>{{ $money($actual) }}</div>
                            @if ($qty !== null && $price !== null && ((float) $qty > 0 || (float) $price > 0))
                                <div class="text-[10px] text-gray-400">{{ $qty }} × {{ number_format((float) $price, 2, ',', ' ') }}</div>
                            @endif
                        </td>
                        <td class="px-2 py-1.5">
                            <span @class([
                                'inline-flex rounded px-1.5 py-0.5 text-[11px] font-medium',
                                'bg-emerald-100 text-emerald-800' => $status === 'Opłacone',
                                'bg-rose-100 text-rose-800' => $status === 'Nieopłacone',
                                'bg-gray-100 text-gray-700' => ! in_array($status, ['Opłacone', 'Nieopłacone'], true),
                            ])>{{ $status }}</span>
                            @if ($accepted)
                                <div class="mt-0.5 text-[10px] text-emerald-700">zaakceptowane</div>
                            @endif
                        </td>
                        <td class="px-2 py-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $note !== '' ? $note : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 dark:bg-white/5">
                <tr class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    <td class="px-2 py-2" colspan="5">Suma</td>
                    <td class="px-2 py-2 text-right tabular-nums whitespace-nowrap">{{ $money($plannedSum) }}</td>
                    <td class="px-2 py-2 text-right tabular-nums whitespace-nowrap">{{ $money($actualSum) }}</td>
                    <td class="px-2 py-2" colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
