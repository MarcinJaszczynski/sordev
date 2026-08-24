@props(['schemas' => []])

@php
    $items = collect($schemas)->filter()->values();
@endphp

@foreach($items as $schema)
    <script type="application/ld+json">
        {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
    </script>
@endforeach
