@props([
    'active' => null,
    'label' => null,
    'icon' => null,
    'indicator' => null,
    'list' => true,
    'scrollable' => false,
    'customIcon' => null,
])
{{--
  Panele TipTapa (kolor, nagłówki) nie mogą żyć w drzewie pod .fi-layout overflow-x-clip
  ani pod overflow-y-auto modala — nawet strategy:fixed jest wtedy ucinane.
  Popover API = top layer (nad overflow). Pozycja z getBoundingClientRect przycisku.
--}}
<div
    x-data="{
        indicator: () => {{ $indicator ?? 'null' }},
        placePanel() {
            const panel = this.$refs.panel
            const btn = this.$refs.trigger?.querySelector('button') || this.$refs.trigger
            if (! panel || ! btn) return
            const rect = btn.getBoundingClientRect()
            const gap = 6
            const width = Math.max(panel.offsetWidth || 224, 224)
            let left = rect.left
            left = Math.min(left, window.innerWidth - width - 8)
            left = Math.max(8, left)
            let top = rect.bottom + gap
            panel.style.position = 'fixed'
            panel.style.left = `${left}px`
            panel.style.top = `${top}px`
            panel.style.right = 'auto'
            panel.style.bottom = 'auto'
            panel.style.margin = '0'
            panel.style.inset = 'unset'
            // jeśli nie mieści się w dół — nad przyciskiem
            this.$nextTick(() => {
                const h = panel.offsetHeight || 0
                if (top + h > window.innerHeight - 8 && rect.top > h + gap) {
                    panel.style.top = `${Math.max(8, rect.top - h - gap)}px`
                }
            })
        },
        init() {
            const panel = this.$refs.panel
            if (! panel) return

            panel.toggle = () => {
                if (panel.matches(':popover-open')) {
                    panel.hidePopover()
                    return
                }
                panel.showPopover()
                this.$nextTick(() => this.placePanel())
            }
            panel.close = () => {
                if (panel.matches(':popover-open')) {
                    panel.hidePopover()
                }
            }

            panel.addEventListener('toggle', (e) => {
                if (e.newState === 'open') {
                    this.$nextTick(() => this.placePanel())
                }
            })

            const onDocPointer = (e) => {
                if (! panel.matches(':popover-open')) return
                const t = e.target
                if (panel.contains(t)) return
                if (this.$refs.trigger && this.$refs.trigger.contains(t)) return
                panel.hidePopover()
            }
            const onKey = (e) => {
                if (e.key === 'Escape' && panel.matches(':popover-open')) {
                    panel.hidePopover()
                }
            }
            document.addEventListener('pointerdown', onDocPointer, true)
            document.addEventListener('keydown', onKey, true)

            window.addEventListener('resize', () => {
                if (panel.matches(':popover-open')) this.placePanel()
            })
            window.addEventListener('scroll', () => {
                if (panel.matches(':popover-open')) this.placePanel()
            }, true)
        }
    }"
    class="relative"
    x-on:close-panel="$refs.panel.close()"
>
    @if ($indicator)
        <div
            x-text="{{ $indicator }}"
            class="text-[0.625rem] absolute top-0 right-0 font-mono text-gray-800 dark:text-gray-300 pointer-events-none"
            x-bind:class="{ 'hidden': ! indicator() }"
        ></div>
    @endif

    <div x-ref="trigger">
        <x-filament-tiptap-editor::button
            action="$refs.panel.toggle"
            :active="$active"
            :label="$label"
            :icon="$icon"
        >
            @if (! $icon)
                {!! $customIcon !!}
            @endif
        </x-filament-tiptap-editor::button>
    </div>

    <div
        x-ref="panel"
        popover="manual"
        @class([
            'tiptap-panel z-[9999] bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-gray-950/10 dark:ring-white/10 p-0',
            'overflow-y-auto max-h-64' => ! $active && $list,
        ])
        style="position: fixed; margin: 0; inset: unset;"
    >
        @if ($list)
            <ul class="relative text-sm divide-y rounded-md overflow-hidden divide-gray-200 dark:divide-gray-700 min-w-[144px] text-gray-800 dark:text-white">
                {{ $slot }}
            </ul>
        @else
            <div class="relative p-2 min-w-[14rem] max-w-[18rem]">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>
