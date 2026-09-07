<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filtry -->
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                <div class="flex items-center justify-between gap-3">
                    <span class="font-bold text-base">Filtry</span>
                    <x-filament::button
                        color="gray"
                        size="sm"
                        outlined
                        wire:click="resetLayoutState"
                    >
                        Resetuj uklad
                    </x-filament::button>
                </div>
            </x-slot>
            <form class="max-w-full space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('od daty') }}
                        </label>
                        <input 
                            type="date" 
                            wire:model.lazy="selectedDateFrom"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('do daty') }}
                        </label>
                        <input 
                            type="date" 
                            wire:model.lazy="selectedDateTo"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Oś czasu
                        </label>
                        <select
                            wire:model.lazy="selectedDateAxis"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="paid_at">Data płatności</option>
                            <option value="created_at">Data utworzenia wpisu</option>
                            <option value="event_start">Data wyjazdu imprezy</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('Status płatności') }}
                        </label>
                        <select 
                            wire:model.lazy="selectedPaymentStatus"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="">-- Wszystkie --</option>
                            @foreach(\App\Models\EventSettlementCost::$paymentStatuses as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('Kontraktor') }}
                        </label>
                        <select 
                            wire:model.lazy="selectedContractor"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="">-- Wszyscy --</option>
                            @foreach(\App\Models\Contractor::orderBy('name')->get() as $contractor)
                                <option value="{{ $contractor->id }}">{{ $contractor->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('Impreza') }}
                        </label>
                        <select 
                            wire:model.lazy="selectedEvent"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="">-- Wszystkie --</option>
                            @foreach(\App\Models\Event::orderBy('name')->get() as $event)
                                <option value="{{ $event->id }}">{{ $event->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('Płaci') }}
                        </label>
                        <select 
                            wire:model.lazy="selectedPaidBy"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="">-- Wszyscy --</option>
                            @foreach(\App\Models\EventSettlementCost::$paidByOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
        </x-filament::section>

        <!-- Statystyki -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($this->getStats() as $stat)
                {{ $stat->render('filament::widgets.stats-overview-widget.stat') }}
            @endforeach
        </div>

        <!-- Tabela wydatków -->
        <x-filament::section heading="Wydatki" class="pt-0">
            <div class="overflow-x-auto">
                {{ $this->table }}
            </div>
        </x-filament::section>

        <!-- Wykresy -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-filament::section heading="Wydatki po statusie płatności" collapsible>
                <div x-data="{
                    labels: @js(array_keys(\App\Models\EventSettlementCost::$paymentStatuses)),
                    data: @js(array_values($this->getExpensesByStatusData())),
                    colors: ['#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#3b82f6', '#ec4899']
                }">
                    <canvas x-init="
                        new Chart(
                            $el,
                            {
                                type: 'doughnut',
                                data: {
                                    labels: labels.map(l => @js(\App\Models\EventSettlementCost::$paymentStatuses)[l]),
                                    datasets: [{
                                        data: data,
                                        backgroundColor: colors,
                                        borderColor: '#ffffff'
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: true,
                                    plugins: {
                                        legend: { position: 'bottom' }
                                    }
                                }
                            }
                        )
                    "></canvas>
                </div>
            </x-filament::section>

            <x-filament::section heading="Wydatki po kontrahentach (rzeczywiste)" collapsible>
                <div x-data="{
                    labels: @js(array_keys($this->getExpensesByContractorData())),
                    data: @js(array_values($this->getExpensesByContractorData()))
                }">
                    <canvas x-init="
                        new Chart(
                            $el,
                            {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: 'Wartość (PLN)',
                                        data: data,
                                        backgroundColor: '#10b981',
                                        borderColor: '#059669',
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: true,
                                    indexAxis: 'y',
                                    plugins: {
                                        legend: { display: false }
                                    }
                                }
                            }
                        )
                    "></canvas>
                </div>
            </x-filament::section>

            <x-filament::section heading="Wydatki w czasie (rzeczywiste)" collapsible>
                <div x-data="{
                    labels: @js(array_keys($this->getExpensesByDateData())),
                    data: @js(array_values($this->getExpensesByDateData()))
                }">
                    <canvas x-init="
                        new Chart(
                            $el,
                            {
                                type: 'line',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: 'Wydane (PLN)',
                                        data: data,
                                        borderColor: '#3b82f6',
                                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                        tension: 0.4,
                                        fill: true,
                                        pointRadius: 4,
                                        pointBackgroundColor: '#3b82f6'
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: true,
                                    plugins: {
                                        legend: { display: true }
                                    }
                                }
                            }
                        )
                    "></canvas>
                </div>
            </x-filament::section>

            <x-filament::section heading="Wydatki po płacącym" collapsible>
                <div x-data="{
                    labels: @js(array_map(fn($k) => \App\Models\EventSettlementCost::$paidByOptions[$k], array_keys($this->getExpensesByPaidByData()))),
                    data: @js(array_values($this->getExpensesByPaidByData())),
                    colors: ['#8b5cf6', '#ec4899']
                }">
                    <canvas x-init="
                        new Chart(
                            $el,
                            {
                                type: 'pie',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        data: data,
                                        backgroundColor: colors,
                                        borderColor: '#ffffff'
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: true,
                                    plugins: {
                                        legend: { position: 'bottom' }
                                    }
                                }
                            }
                        )
                    "></canvas>
                </div>
            </x-filament::section>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endpush
</x-filament-panels::page>
