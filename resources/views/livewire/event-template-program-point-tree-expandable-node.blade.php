<li data-id="{{ $node['id'] }}" class="flex items-start gap-2 py-1 filament-tables-row bg-white">
    <div class="flex-1 flex items-center gap-2 cursor-move">
        <span wire:click="{{ $toggle }}({{ $node['id'] }})" style="cursor:pointer; min-width: 2em;">
            @if (!empty($node['children']))
                <strong class="text-primary-600">[{{ in_array($node['id'], $expanded) ? '-' : '+' }}]</strong>
            @else
                <span class="inline-block w-4"></span>
            @endif
        </span>
        <span>{{ $node['name'] }}</span>
    </div>
    <div class="flex gap-2">
        <button class="filament-link text-primary-600 hover:underline mr-2"
            wire:click.prevent="edit({{ $node['id'] }})">Edytuj</button>
        <button class="filament-link text-danger-600 hover:underline mr-2"
            wire:click.prevent="delete({{ $node['id'] }})">Usuń</button>
        <button class="filament-link text-success-600 hover:underline"
            wire:click.prevent="addChild({{ $node['id'] }})">Dodaj podpunkt</button>
    </div>
    @if (!empty($node['children']) && in_array($node['id'], $expanded))
        <ul x-data x-init="window.initProgramPointTreeDnD($el, $wire, {{ $node['id'] }})" data-parent-id="{{ $node['id'] }}"
            class="ml-6 border-l border-gray-200 pl-2">
            @foreach ($node['children'] as $child)
                @include('livewire.event-template-program-point-tree-expandable-node', [
                    'node' => $child,
                    'expanded' => $expanded,
                    'toggle' => $toggle,
                ])
            @endforeach
            </ul>
    @endif
</li>
