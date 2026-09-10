{{-- Jeden root: skrypt jako rodzeństwo psuł wire:id (selectTask szedł w stronę rodzica). --}}
<div
    @class([
        'fi-resource-relation-manager flex flex-col gap-y-4',
        'tasks-split-view tasks-split-view--embedded',
        'tasks-split-view--has-selection' => filled($this->selectedTaskId),
    ])
    x-data
    x-init="
        const go = () => window.scrollTasksSplitEditorIntoView?.();
        if ($wire.selectedTaskId) {
            setTimeout(go, 100);
            setTimeout(go, 300);
        }
        $wire.$watch('selectedTaskId', (value) => {
            if (! value) return;
            queueMicrotask(go);
            setTimeout(go, 100);
            setTimeout(go, 300);
        });
    "
>
    @include('filament.tasks.partials.tasks-split-scroll-script')

    <x-filament-panels::resources.tabs />

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_RELATION_MANAGER_BEFORE, scopes: $this->getRenderHookScopes()) }}

    <div class="tasks-split-view__shell">
        <div class="tasks-split-view__list min-h-0 overflow-auto">
            <div class="tasks-split-view__list-toolbar">
                <label class="tasks-split-view__sort">
                    <span class="tasks-split-view__sort-label">Sortuj</span>
                    <select wire:model.live="listSort" class="tasks-split-view__sort-select task-split-select">
                        @foreach ($this->listSortOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            {{ $this->table }}
        </div>

        <div class="tasks-split-view__detail" id="tasks-split-detail-panel">
            @include('filament.tasks.partials.task-split-detail')
        </div>
    </div>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_RELATION_MANAGER_AFTER, scopes: $this->getRenderHookScopes()) }}

    <x-filament-panels::unsaved-action-changes-alert />
</div>
