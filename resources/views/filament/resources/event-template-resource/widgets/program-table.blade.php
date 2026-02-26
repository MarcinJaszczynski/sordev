<x-filament-widgets::widget>
@php
    // Pobieranie punktów programu
    $programDays = collect();
    $record = $record ?? $this->record ?? $this->getRecord() ?? null;
    if ($record) {
        $programPoints = $record->programPoints()
            ->withPivot(['day', 'order', 'notes', 'include_in_program', 'include_in_calculation', 'active'])
            ->orderBy('event_template_event_template_program_point.day')
            ->orderBy('event_template_event_template_program_point.order')
            ->get();
        for ($i = 1; $i <= $record->duration_days; $i++) {
            $daysPoints = $programPoints->filter(fn($point) => $point->pivot->day == $i && $point->pivot->include_in_program);
            $programDays->put($i, $daysPoints);
        }
    }
@endphp

@if($programDays->isNotEmpty())
    <h2 class="text-xl font-bold mb-4">Program imprezy</h2>
@endif

@php
    // Ustal stały colgroup dla wszystkich tabel
    $colgroup = '<col style="width: 20%" /><col style="width: 20%" /><col style="width: 20%" /><col style="width: 20%" /><col style="width: 20%" />';
@endphp

@foreach($programDays as $day => $points)
    <div class="mb-8">
        <h3 class="text-lg font-semibold mb-3">Dzień {{ $day }}</h3>
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 table-fixed">
                {!! $colgroup !!}
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Punkt programu</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Opis do programu</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uwagi dla biura</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uwagi dla pilota</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obrazek</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($points as $index => $point)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                            <td class="px-4 py-2 align-top">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $point->name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Czas trwania: {{ $point->duration_hours }}:{{ str_pad($point->duration_minutes, 2, '0', STR_PAD_LEFT) }}
                                </div>
                            </td>
                            <td class="px-4 py-2 align-top text-sm text-gray-900 dark:text-white">{{ $point->description }}</td>
                            <td class="px-4 py-2 align-top text-sm text-gray-900 dark:text-white">{{ $point->office_notes }}</td>
                            <td class="px-4 py-2 align-top text-sm text-gray-900 dark:text-white">{{ $point->pilot_notes }}</td>
                            <td class="px-4 py-2 align-top">
                                @if($point->featured_image)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($point->featured_image) }}" alt="miniaturka" style="height:75px; width:auto; max-width:100%; object-fit:cover; border-radius:0.5rem;" />
                                @else
                                    <span class="text-gray-400 text-xs">Brak</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-2 text-center text-gray-500">Brak punktów programu dla tego dnia</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endforeach

@if($programDays->every(fn($points) => $points->isEmpty()))
    <div class="text-center py-4 text-gray-500">
        Nie dodano jeszcze żadnych punktów programu
    </div>
@endif
</x-filament-widgets::widget>