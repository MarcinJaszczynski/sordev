<x-filament-panels::page>
    @php
        $schedule = $this->getScheduleInfo();
    @endphp
    <div class="space-y-8">

        <x-filament::section>
            <x-slot name="heading">Harmonogram i retencja</x-slot>
            <x-slot name="description">
                Automatyczne kopie uruchamia cron serwera: <code>php artisan schedule:run</code> co minutę.
            </x-slot>
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">Harmonogram</dt><dd class="font-medium">{{ $schedule['enabled'] ? 'Włączony' : 'Wyłączony' }} ({{ $schedule['cron'] }})</dd></div>
                <div><dt class="text-gray-500">Komponenty</dt><dd class="font-medium">{{ $schedule['components'] }}</dd></div>
                <div><dt class="text-gray-500">Retencja</dt><dd class="font-medium">{{ $schedule['retention'] }} ostatnich kopii</dd></div>
            </dl>
            <div class="mt-4">
                <x-filament::button wire:click="pruneOldBackups" size="sm" color="gray" icon="heroicon-o-trash">
                    Usuń stare kopie (zostaw {{ $schedule['retention'] }})
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- Sekcja: Utwórz kopię zapasową --}}
        <x-filament::section>
            <x-slot name="heading">Utwórz kopię zapasową</x-slot>
            <x-slot name="description">Pełna aplikacja = baza + cały storage + kod (bez vendor). Na serwerze uruchom potem composer install i npm run build.</x-slot>

            <div class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    <x-filament::button size="sm" color="gray" wire:click="selectFullApplication" icon="heroicon-o-server-stack">
                        Cała aplikacja
                    </x-filament::button>
                    <x-filament::button size="sm" color="gray" wire:click="selectDataOnly" icon="heroicon-o-circle-stack">
                        Tylko dane
                    </x-filament::button>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:gap-6">
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        <input type="checkbox" wire:model.live="includeDb"
                               class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
                        Baza danych
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        <input type="checkbox" wire:model.live="includeStorage"
                               class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
                        Storage (cały katalog storage/)
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        <input type="checkbox" wire:model.live="includeCode"
                               class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
                        Kod aplikacji
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        <input type="checkbox" wire:model.live="includeEnv"
                               class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
                        Konfiguracja (.env)
                    </label>
                </div>

                @if ($includeEnv)
                    <div class="rounded-lg bg-warning-50 p-3 text-sm text-warning-700 dark:bg-warning-900/20 dark:text-warning-400">
                        ⚠ Plik .env zawiera hasła i klucze dostępowe. Upewnij się, że archiwum będzie przechowywane w bezpiecznym miejscu.
                    </div>
                @endif

                <div>
                    <x-filament::button
                        wire:click="createBackup"
                        wire:loading.attr="disabled"
                        wire:target="createBackup"
                        icon="heroicon-o-archive-box-arrow-down"
                    >
                        <span wire:loading.remove wire:target="createBackup">Utwórz kopię zapasową</span>
                        <span wire:loading wire:target="createBackup" class="inline-flex items-center gap-2">
                            <x-filament::loading-indicator class="h-4 w-4" />
                            Tworzenie...
                        </span>
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        {{-- Sekcja: Istniejące kopie --}}
        <x-filament::section>
            <x-slot name="heading">Istniejące kopie zapasowe</x-slot>
            <x-slot name="description">Lista archiwów w katalogu storage/backups/</x-slot>

            @php $backups = $this->getBackups(); @endphp

            @if (empty($backups))
                <div class="text-sm text-gray-500 dark:text-gray-400 py-4 text-center">
                    Brak kopii zapasowych.
                </div>
            @endif

            @if (! empty($backups))
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-4 py-3">Data</th>
                                <th class="px-4 py-3">Komponenty</th>
                                <th class="px-4 py-3">Rozmiar</th>
                                <th class="px-4 py-3">Baza</th>
                                <th class="px-4 py-3">Pliki</th>
                                <th class="px-4 py-3 text-right">Akcje</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($backups as $backup)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30">
                                    <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900 dark:text-white">
                                        {{ \Carbon\Carbon::parse($backup['created_at'])->format('Y-m-d H:i') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex gap-1 flex-wrap">
                                            @foreach ($backup['components'] as $comp)
                                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium
                                                    {{ $comp === 'db' ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : '' }}
                                                    {{ $comp === 'storage' ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                                    {{ $comp === 'code' ? 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' : '' }}
                                                    {{ $comp === 'env' ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : '' }}
                                                ">{{ $comp }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        {{ $backup['size'] }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        {{ $backup['db_size'] ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        @php
                                            $storageFiles = $backup['storage_files'] ?? null;
                                            $storageScope = $backup['storage_scope'] ?? '';
                                            $codeFiles = $backup['code_files'] ?? null;
                                        @endphp
                                        @if ($storageFiles !== null)
                                            {{ $storageFiles }} pl.
                                            @if ($storageScope === 'full')
                                                <span class="text-xs text-gray-400">(storage)</span>
                                            @endif
                                        @endif
                                        @if ($storageFiles === null)
                                            —
                                        @endif
                                        @if ($codeFiles)
                                            <br><span class="text-xs">{{ $codeFiles }} pl. kodu</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.backups.download', $backup['filename']) }}"
                                               class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                                <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                                                Pobierz
                                            </a>
                                            <button
                                                wire:click="deleteBackup('{{ $backup['filename'] }}')"
                                                wire:confirm="Czy na pewno chcesz usunąć kopię {{ $backup['filename'] }}?"
                                                class="inline-flex items-center gap-1 text-sm font-medium text-danger-600 hover:text-danger-500 dark:text-danger-400">
                                                <x-heroicon-m-trash class="w-4 h-4" />
                                                Usuń
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        {{-- Sekcja: Przywróć z kopii --}}
        <x-filament::section>
            <x-slot name="heading">Przywróć z kopii zapasowej</x-slot>
            <x-slot name="description">Wgraj archiwum ZIP i przywróć wybrane elementy.</x-slot>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Plik kopii zapasowej (.zip)
                    </label>
                    <input type="file" wire:model.live.debounce.500ms="restoreFile" accept=".zip"
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-900/30 dark:file:text-primary-400 dark:text-gray-400">

                    <div wire:loading wire:target="restoreFile" class="mt-2 text-sm text-gray-500">
                        <x-filament::loading-indicator class="h-4 w-4 inline" /> Wczytywanie pliku...
                    </div>
                </div>

                @if ($restoreManifest)
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-3">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Zawartość archiwum</h4>
                        <div class="grid grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <span>Data utworzenia:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($restoreManifest['created_at'] ?? '')->format('Y-m-d H:i') }}</span>
                            <span>PHP:</span>
                            <span>{{ $restoreManifest['php_version'] ?? '?' }}</span>
                            <span>Sterownik DB:</span>
                            <span>{{ $restoreManifest['db_driver'] ?? '?' }}</span>
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Komponenty do przywrócenia:</p>
                            <div class="flex flex-col gap-2">
                                @if (in_array('db', $restoreManifest['components'] ?? []))
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" wire:model.live="restoreDb"
                                               class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm">
                                        Baza danych
                                        @isset($restoreManifest['db_size_bytes'])
                                            <span class="text-gray-400 text-xs">({{ $this->formatBytes($restoreManifest['db_size_bytes']) }})</span>
                                        @endisset
                                    </label>
                                @endif
                                @if (in_array('storage', $restoreManifest['components'] ?? []))
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" wire:model.live="restoreStorage"
                                               class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm">
                                        Storage
                                        @isset($restoreManifest['storage_files'])
                                            <span class="text-gray-400 text-xs">({{ $restoreManifest['storage_files'] }} plików)</span>
                                        @endisset
                                    </label>
                                @endif
                                @if (in_array('code', $restoreManifest['components'] ?? []))
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" wire:model.live="restoreCode"
                                               class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm">
                                        Kod aplikacji
                                        @isset($restoreManifest['code_files'])
                                            <span class="text-gray-400 text-xs">({{ $restoreManifest['code_files'] }} plików)</span>
                                        @endisset
                                    </label>
                                @endif
                                @if (in_array('env', $restoreManifest['components'] ?? []))
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" wire:model.live="restoreEnv"
                                               class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm">
                                        Konfiguracja (.env)
                                    </label>
                                @endif
                            </div>
                        </div>

                        @if ($restoreEnv)
                            <div class="rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-900/20 dark:text-danger-400">
                                ⚠ Przywrócenie .env nadpisze bieżącą konfigurację, w tym hasła i klucze API.
                            </div>
                        @endif

                        @if (! $showRestoreConfirm)
                            <x-filament::button wire:click="confirmRestore" color="warning" icon="heroicon-o-arrow-path"
                                                :disabled="!$restoreDb && !$restoreStorage && !$restoreCode && !$restoreEnv">
                                Przywróć z kopii
                            </x-filament::button>
                        @endif

                        @if ($showRestoreConfirm)
                            <div class="rounded-lg bg-danger-50 dark:bg-danger-900/20 p-4 space-y-3">
                                <p class="text-sm font-semibold text-danger-700 dark:text-danger-400">
                                    Czy na pewno chcesz przywrócić dane z kopii zapasowej?
                                </p>
                                <p class="text-sm text-danger-600 dark:text-danger-400">
                                    Operacja nadpisze istniejące dane ({{ implode(', ', array_filter([
                                        $restoreDb ? 'baza danych' : null,
                                        $restoreStorage ? 'storage' : null,
                                        $restoreCode ? 'kod aplikacji' : null,
                                        $restoreEnv ? 'plik .env' : null,
                                    ])) }}).
                                    Przed przywróceniem bazy zostanie automatycznie utworzony pre-backup.
                                </p>
                                <div class="flex gap-3">
                                    <x-filament::button
                                        wire:click="executeRestore"
                                        color="danger"
                                        icon="heroicon-o-exclamation-triangle"
                                        wire:loading.attr="disabled"
                                        wire:target="executeRestore"
                                        :disabled="$isRestoring"
                                    >
                                        <span wire:loading.remove wire:target="executeRestore">Tak, przywróć</span>
                                        <span wire:loading wire:target="executeRestore" class="inline-flex items-center gap-2">
                                            <x-filament::loading-indicator class="h-4 w-4" />
                                            Przywracanie...
                                        </span>
                                    </x-filament::button>
                                    <x-filament::button wire:click="cancelRestore" color="gray" :disabled="$isRestoring">
                                        Anuluj
                                    </x-filament::button>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
