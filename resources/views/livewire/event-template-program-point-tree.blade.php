<ul>
    @foreach ($tree as $node)
        <li>
            {{ $node['name'] }}
            @if (!empty($node['children']))
                <ul style="margin-left: 1.5em;">
                    @foreach ($node['children'] as $child)
                        @include('livewire.event-template-program-point-tree-node', ['node' => $child])
                    @endforeach
                </ul>
            @endif
        </li>
    @endforeach
</ul>