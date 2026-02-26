@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto bg-white shadow rounded-lg p-6 mt-8">
    <h2 class="text-2xl font-bold mb-4">Edytuj opis ceny imprezy</h2>
    <form method="POST" action="{{ route('event.price-description.update', $eventId) }}">
        @csrf
        <div class="mb-4">
            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Opis (możesz używać <b>, <ul>, <li> itd.)</label>
            <textarea id="description" name="description" rows="8" class="w-full border rounded p-2">{{ old('description', $description) }}</textarea>
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Zapisz</button>
    </form>
</div>
@endsection
