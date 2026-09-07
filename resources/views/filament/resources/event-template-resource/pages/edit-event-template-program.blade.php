<x-filament-panels::page>
    @unless ($this->canMutateEventTemplateNow())
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
            Podgląd programu szablonu. Żeby zmieniać punkty, kliknij <strong>Edytuj szablon</strong> i potwierdź.
        </div>
    @endunless

    <div>
        <livewire:event-program-tree-editor
            :eventTemplate="$this->eventTemplate"
            :readOnly="! $this->canMutateEventTemplateNow()"
        />
    </div>
</x-filament-panels::page>
