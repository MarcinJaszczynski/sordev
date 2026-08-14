<x-filament-tiptap-editor::dropdown-button
    label="{{ trans('filament-tiptap-editor::editor.color.label') }}"
    active="color"
    :list="false"
>
    <x-slot:customIcon>
        <span class="tiptap-color-a-icon inline-flex flex-col items-center justify-center w-5 h-5 leading-none select-none" aria-hidden="true">
            <span class="text-[15px] font-semibold tracking-tight">A</span>
            <span class="tiptap-color-a-bar block w-4 h-0.5 rounded-sm mt-0.5 bg-gradient-to-r from-red-500 via-amber-400 to-blue-500"></span>
        </span>
    </x-slot:customIcon>

    <div
        x-data="{
            state: editor().getAttributes('textStyle').color || '#000000',

            init: function () {
                if (!(this.state === null || this.state === '')) {
                    this.setState(this.state)
                }

                this.$watch('state', (value) => {
                    if (! value.startsWith('#')) {
                        this.state = `#${value}`
                    }
                })
            },

            setState: function (value) {
                this.state = value
            }
        }"
        class="tiptap-color-picker-body relative w-full"
    >
        <tiptap-hex-color-picker
            class="tiptap-hex-picker"
            x-bind:color="state"
            x-on:color-changed="setState($event.detail.value)"
        ></tiptap-hex-color-picker>

        <label
            class="fi-input-wrp mt-2 flex rounded-lg shadow-sm ring-1 transition duration-75 bg-white dark:bg-white/5 [&:not(:has(.fi-ac-action:focus))]:focus-within:ring-2 ring-gray-950/10 dark:ring-white/20 [&:not(:has(.fi-ac-action:focus))]:focus-within:ring-primary-600 dark:[&:not(:has(.fi-ac-action:focus))]:focus-within:ring-primary-500 fi-fo-text-input overflow-hidden"
        >
            <input
                x-model="state"
                class="fi-input block w-full border-none py-1.5 text-base text-gray-950 transition duration-75 placeholder:text-gray-400 focus:ring-0 disabled:text-gray-500 dark:text-white sm:text-sm sm:leading-6 bg-white/0 ps-3 pe-3"
                placeholder="#000000"
            />
            <span class="sr-only">{{ trans('filament-tiptap-editor::editor.color.input_label') }}</span>
        </label>

        @if(filled(config('filament-tiptap-editor.preset_colors')))
        <div class="mt-2 flex flex-wrap justify-start gap-1.5">
            @foreach(config('filament-tiptap-editor.preset_colors') as $name => $value)
                <button
                    type="button"
                    wire:key="tiptap-preset-{{ $name }}"
                    x-tooltip.raw="{{ $name }}"
                    class="rounded-md w-6 h-6 cursor-pointer ring-1 ring-gray-950/10 dark:ring-white/20 hover:ring-2 hover:ring-primary-500"
                    style="background-color:{{ $value }};"
                    x-on:click="setState('{{ $value }}');"
                    aria-label="{{ $name }}"
                ></button>
            @endforeach
        </div>
        @endif

        <div class="w-full flex gap-2 mt-2">
            <x-filament::button
                x-on:click="editor().chain().focus().setColor(state).run(); $dispatch('close-panel')"
                size="sm"
                class="flex-1"
            >
                {{ trans('filament-tiptap-editor::editor.color.choose') }}
            </x-filament::button>

            <x-filament::button
                x-on:click="editor().chain().focus().unsetColor().run(); $dispatch('close-panel')"
                size="sm"
                class="flex-1"
                color="gray"
            >
                {{ trans('filament-tiptap-editor::editor.color.remove') }}
            </x-filament::button>
        </div>
    </div>
</x-filament-tiptap-editor::dropdown-button>
