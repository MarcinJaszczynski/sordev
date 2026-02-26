@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto bg-white shadow rounded-lg p-6 mt-8">
    <h2 class="text-2xl font-bold mb-4">Co zawiera cena imprezy?</h2>
    <div class="prose prose-lg">
        {!! $description !!}
    </div>
</div>
@endsection
