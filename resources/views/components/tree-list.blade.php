<div>
    @if(!empty($tree))
        @foreach($tree as $node)
            <div class="tree-node flex items-center mb-2" data-id="{{ $node['id'] }}">
                <span class="drag-handle cursor-move mr-2">&#9776;</span>
                <span class="font-medium">{{ $node['name'] }}</span>
                <button class="ml-2 text-xs text-blue-600"
                    onclick="if(confirm('Przejść do ekranu edycji punktu programu?')){ window.location.href='/admin/event-template-program-points/{{ $node['id'] }}/edit'; } return false;"
                >Edytuj</button>
                <button class="ml-2 text-xs text-red-600" wire:click="$emitUp('deletePoint', {{ $node['id'] }})">Usuń</button>
                @if(!empty($node['children']))
                    <div class="tree-children ml-6">
                        @component('components.tree-list', ['tree' => $node['children']])
                        @endcomponent
                    </div>
                @endif
            </div>
        @endforeach
    @endif
</div>